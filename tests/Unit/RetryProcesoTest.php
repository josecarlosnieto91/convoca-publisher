<?php

/**
 * La cola de reintentos: la parte del plugin donde un error no se ve.
 *
 * Aquí se decide si una publicación se pierde para siempre, si se publica dos veces porque dos
 * cron se solapan, o si se avisa a alguien de que lleva cinco intentos fallando. Estaba al 39,4 %:
 * se probaba el backoff (que es lo fácil) y no el proceso de la cola (que es lo que duele).
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Retry;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class RetryProcesoTest extends TestCase
{
    protected function setUp(): void
    {
        // Otras clases de prueba sustituyen $wpdb por su propio doble: aquí se parte del del
        // arnés para que el resultado no dependa del orden en que se ejecuten las clases.
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['_cp_test_db'] = [];
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_filters'] = [];
        $GLOBALS['_cp_test_http_queue'] = [];
        $GLOBALS['_cp_test_titles'] = [10 => 'Fiesta del socio'];
        $GLOBALS['_cp_test_status'] = [10 => 'publish'];
        $GLOBALS['_cp_test_missing'] = [];
        unset($GLOBALS['_cp_publisher_stub']);

        $ref = new \ReflectionProperty(\ConvocaPublisher\Publisher::class, 'instance');
        $ref->setValue(null, null);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_cp_test_db'],
            $GLOBALS['_cp_test_options'],
            $GLOBALS['_cp_test_filters'],
            $GLOBALS['_cp_test_http_queue'],
            $GLOBALS['_cp_test_titles'],
            $GLOBALS['_cp_test_status'],
            $GLOBALS['_cp_test_missing'],
            $GLOBALS['_cp_publisher_stub']
        );

        // El publicador es un singleton: si se queda con los canales de mentira de esta prueba,
        // las siguientes clases se encuentran un plugin ya «inicializado» y ven otra cosa.
        $ref = new \ReflectionProperty(\ConvocaPublisher\Publisher::class, 'instance');
        $ref->setValue(null, null);
    }

    private function canal(bool $funciona = true): FakeChannel
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook', $funciona, 'la red dijo que no');

        // La cola pide el canal por convoca_publisher()->get_channel(), que es la función del
        // plugin y no el singleton: se montan los dos para que el camino sea el de verdad.
        \ConvocaPublisher\Publisher::init(['fb' => $canal]);
        $GLOBALS['_cp_publisher_stub'] = new class ($canal) {
            public function __construct(private object $canal)
            {
            }

            public function get_channels(): array
            {
                return ['fb' => $this->canal];
            }

            public function get_channel(string $channel_id): ?object
            {
                return 'fb' === $channel_id ? $this->canal : null;
            }

            public function publish_post(int $post_id): array
            {
                return [];
            }
        };

        return $canal;
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function entrada(array $fila = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'post_id' => 10,
            'channel' => 'fb',
            'payload' => 'Mensaje guardado',
            'error_text' => '',
            'attempts' => 0,
            'status' => Retry::STATUS_PENDING,
            'last_attempt' => null,
            'next_attempt' => '2026-10-23 00:00:00',
        ], $fila);
    }

    // ── creación de la tabla ────────────────────────────────────────────────────────────────

    public function test_la_tabla_no_se_crea_dos_veces(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_retry_table_version'] = 2;

        Retry::create_table();

        $this->assertArrayNotHasKey('calls', $GLOBALS['_cp_test_db'], 'con la versión al día no se toca la base de datos');
    }

    // ── proceso de la cola ──────────────────────────────────────────────────────────────────

    public function test_si_otro_cron_reclamo_la_fila_no_se_publica_dos_veces(): void
    {
        $canal = $this->canal();
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada()];
        $GLOBALS['_cp_test_db']['update_rc'] = 0; // el claim no entra: otro proceso se la llevó

        Retry::process_queue();

        $this->assertCount(0, $canal->sent, 'una fila reclamada por otro no se publica');
    }

    public function test_una_fila_reclamada_y_publicada_con_exito_se_borra(): void
    {
        $canal = $this->canal();
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada()];

        Retry::process_queue();

        $this->assertCount(1, $canal->sent, 'la publicación se intenta');
        $this->assertContains('delete', $GLOBALS['_cp_test_db']['calls'], 'y al ir bien se borra de la cola');
    }

    public function test_un_post_que_ya_no_esta_publicado_se_descarta_sin_publicar(): void
    {
        $canal = $this->canal();
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada()];
        $GLOBALS['_cp_test_status'][10] = 'draft'; // lo despublicaron después de encolarlo

        Retry::process_queue();

        $this->assertCount(0, $canal->sent);
        $this->assertContains('delete', $GLOBALS['_cp_test_db']['calls'], 'se descarta la fila');
    }

    public function test_un_canal_que_ya_no_existe_se_descarta(): void
    {
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada(['channel' => 'desaparecido'])];
        \ConvocaPublisher\Publisher::init([]);

        Retry::process_queue();

        $this->assertContains('delete', $GLOBALS['_cp_test_db']['calls']);
    }

    public function test_un_fallo_programa_el_siguiente_intento_con_backoff(): void
    {
        $this->canal(false);
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada()];

        Retry::process_queue();

        $updates = $GLOBALS['_cp_test_db']['updates'] ?? [];
        $this->assertNotEmpty($updates);
        // El primer update es el claim (status/last_attempt); el del reintento lleva 'attempts'.
        $reintento = array_values(array_filter($updates, static fn ($u) => isset($u['data']['attempts'])));
        $this->assertNotEmpty($reintento, 'tiene que reprogramarse el intento');
        $data = end($reintento)['data'];

        $this->assertSame(1, $data['attempts'], 'el intento se cuenta');
        $this->assertSame(Retry::STATUS_PENDING, $data['status'], 'vuelve a la cola');
        $this->assertSame('la red dijo que no', $data['error_text'], 'el motivo se guarda para poder mirarlo');
        $this->assertNotEmpty($data['next_attempt'], 'y se programa la próxima vez');
    }

    public function test_al_agotar_los_intentos_queda_fallida_y_avisa(): void
    {
        $this->canal(false);
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada(['attempts' => 4])]; // el siguiente es el quinto
        $GLOBALS['_cp_test_options']['admin_email'] = 'admin@example.com';
        $GLOBALS['_cp_test_mail'] = [];

        Retry::process_queue();

        $updates   = array_values(array_filter($GLOBALS['_cp_test_db']['updates'], static fn ($u) => isset($u['data']['attempts'])));
        $this->assertNotEmpty($updates, 'tiene que registrarse el intento agotado');
        $data = end($updates)['data'];
        $this->assertSame(Retry::STATUS_FAILED, $data['status'], 'tras cinco intentos queda fallida');
        $this->assertSame(5, $data['attempts']);
        $this->assertNull($data['next_attempt'], 'y no se reprograma');

        $this->assertNotEmpty($GLOBALS['_cp_test_mail'] ?? [], 'el administrador tiene que enterarse');
        $this->assertStringContainsString('Fallo definitivo', $GLOBALS['_cp_test_mail'][0]['subject']);
        $this->assertContains('admin@example.com', (array) $GLOBALS['_cp_test_mail'][0]['to'], 'el aviso va a quien administra');
    }

    public function test_sin_correo_de_administracion_no_revienta(): void
    {
        $this->canal(false);
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada(['attempts' => 4])];
        $GLOBALS['_cp_test_mail'] = [];

        Retry::process_queue();

        $updates = array_values(array_filter($GLOBALS['_cp_test_db']['updates'], static fn ($u) => isset($u['data']['attempts'])));
        $this->assertSame(Retry::STATUS_FAILED, end($updates)['data']['status']);
        $this->assertEmpty($GLOBALS['_cp_test_mail'], 'sin destinatario no se manda nada, y no se cae');
    }

    public function test_el_aviso_se_puede_desactivar_con_el_filtro(): void
    {
        $this->canal(false);
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada(['attempts' => 4])];
        $GLOBALS['_cp_test_options']['admin_email'] = 'admin@example.com';
        $GLOBALS['_cp_test_mail'] = [];
        add_filter('convoca_publisher_retry_failed_notify_email', static fn (): bool => false);

        Retry::process_queue();

        $this->assertEmpty($GLOBALS['_cp_test_mail'], 'quien filtre a false no quiere correos');
    }

    public function test_si_la_base_de_datos_falla_en_el_claim_se_salta_la_fila(): void
    {
        $canal = $this->canal();
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada()];
        $GLOBALS['_cp_test_db']['update_rc'] = false;

        Retry::process_queue();

        $this->assertCount(0, $canal->sent);
    }

    public function test_sin_filas_no_hace_nada(): void
    {
        $canal = $this->canal();
        $GLOBALS['_cp_test_db']['results'] = [];

        Retry::process_queue();

        $this->assertCount(0, $canal->sent);
    }

    // ── moderación ─────────────────────────────────────────────────────────────────────────

    public function test_aprobar_una_entrada_que_no_esta_en_revision_no_hace_nada(): void
    {
        $this->canal();
        $GLOBALS['_cp_test_db']['row'] = $this->entrada(['status' => Retry::STATUS_PENDING]);

        $resultado = Retry::approve(1);

        $this->assertFalse($resultado['success']);
        $this->assertNotEmpty($resultado['error']);
    }

    public function test_aprobar_una_entrada_inexistente_no_hace_nada(): void
    {
        $this->canal();
        $GLOBALS['_cp_test_db']['row'] = null;

        $resultado = Retry::approve(99);

        $this->assertFalse($resultado['success']);
    }

    public function test_aprobar_una_entrada_cuyo_post_ya_no_existe_la_limpia(): void
    {
        $this->canal();
        $GLOBALS['_cp_test_db']['row'] = $this->entrada(['status' => 'pending_review']);
        $GLOBALS['_cp_test_missing'] = [10];

        $resultado = Retry::approve(1);

        $this->assertFalse($resultado['success']);
        $this->assertContains('delete', $GLOBALS['_cp_test_db']['calls'], 'la entrada huérfana no se queda');
    }

    public function test_rechazar_una_entrada_inexistente_devuelve_false(): void
    {
        $GLOBALS['_cp_test_db']['row'] = null;

        $this->assertFalse(Retry::reject(99));
    }

    public function test_rechazar_apunta_el_motivo_en_el_historial(): void
    {
        $GLOBALS['_cp_test_db']['row'] = $this->entrada(['status' => 'pending_review']);

        $this->assertTrue(Retry::reject(1));

        $log = $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] ?? [];
        $this->assertCount(1, $log);
        $this->assertSame('Rechazado por moderación.', $log[0]['response']);
        $this->assertSame('fb', $log[0]['channel']);
        $this->assertFalse($log[0]['success']);
    }

    public function test_el_historial_de_rechazos_no_crece_sin_limite(): void
    {
        $GLOBALS['_cp_test_db']['row'] = $this->entrada(['status' => 'pending_review']);
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = array_fill(0, 200, [
            'post_id' => 1, 'title' => 'x', 'channel' => 'fb', 'success' => false, 'time' => '', 'response' => '',
        ]);

        Retry::reject(1);

        $this->assertCount(200, $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'], 'se recorta a 200');
    }

    // ── contadores ─────────────────────────────────────────────────────────────────────────

    public function test_los_contadores_de_la_cola_se_devuelven_como_enteros(): void
    {
        $GLOBALS['_cp_test_db']['var'] = '7';

        $stats = Retry::get_queue_stats();

        $this->assertSame(['pending' => 7, 'pending_review' => 7, 'failed' => 7], $stats, 'string de la base a int');
    }

    public function test_las_entradas_en_revision_se_listan(): void
    {
        $GLOBALS['_cp_test_db']['results'] = [$this->entrada(['status' => 'pending_review'])];

        $items = Retry::get_review_items();

        $this->assertCount(1, $items);
    }

    public function test_si_la_consulta_no_devuelve_array_se_lista_vacio(): void
    {
        $GLOBALS['_cp_test_db']['results'] = [];

        $this->assertSame([], Retry::get_review_items());
    }
}
