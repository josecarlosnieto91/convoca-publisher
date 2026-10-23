<?php

/**
 * La publicación programada: el espaciado, la entrada más antigua que toca, el reintento y el
 * momento en que se pide ayuda.
 *
 * Estaba al 53,1 %. Es de las partes donde un fallo no se ve: si se borra la marca sin haber
 * publicado, el envío desaparece y nadie se entera. Justo ese camino es el que se prueba aquí.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Notifications;
use ConvocaPublisher\Publisher;
use ConvocaPublisher\Queue;
use ConvocaPublisher\Scheduler;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['_cp_test_db'] = [];
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_postmeta'] = [];
        $GLOBALS['_cp_test_titles'] = [10 => 'Fiesta', 11 => 'Otra fiesta'];
        $GLOBALS['_cp_test_status'] = [10 => 'publish', 11 => 'publish'];
        $GLOBALS['_cp_test_missing'] = [];
        $GLOBALS['_cp_test_mail'] = [];

        $ref = new \ReflectionProperty(Publisher::class, 'instance');
        $ref->setValue(null, null);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_cp_test_db'],
            $GLOBALS['_cp_test_options'],
            $GLOBALS['_cp_test_postmeta'],
            $GLOBALS['_cp_test_titles'],
            $GLOBALS['_cp_test_status'],
            $GLOBALS['_cp_test_missing'],
            $GLOBALS['_cp_test_mail']
        );

        $ref = new \ReflectionProperty(Publisher::class, 'instance');
        $ref->setValue(null, null);
    }

    private function conPublisher(FakeChannel $canal): void
    {
        Publisher::init(['fb' => $canal]);
    }

    /**
     * @param array<int, object> $filas
     */
    private function conFilas(array $filas): void
    {
        $GLOBALS['_cp_test_db']['results'] = $filas;
    }

    // ── intervalo propio del cron ──────────────────────────────────────────────────────────

    public function test_anade_el_intervalo_de_quince_minutos(): void
    {
        $schedules = Scheduler::add_cron_interval(['hourly' => ['interval' => 3600, 'display' => 'Cada hora']]);

        $this->assertArrayHasKey('every_15min', $schedules);
        $this->assertSame(900, $schedules['every_15min']['interval']);
        $this->assertArrayHasKey('hourly', $schedules, 'los intervalos de otros no se pisan');
    }

    // ── espaciado ──────────────────────────────────────────────────────────────────────────

    public function test_si_no_toca_por_espaciado_no_se_publica_nada(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        // Último envío hace un segundo y el intervalo por defecto son 30 minutos.
        $GLOBALS['_cp_test_options'][Queue::LAST_SENT_OPTION] = time() - 1;
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $this->assertCount(0, $canal->sent, 'el espaciado tiene que frenar la publicación');
        $this->assertArrayNotHasKey('delete', array_flip($GLOBALS['_cp_test_db']['calls'] ?? []), 'y no se borra la marca');
    }

    public function test_sin_entradas_que_toquen_no_hace_nada(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $GLOBALS['_cp_test_options'][Queue::LAST_SENT_OPTION] = 0;
        $this->conFilas([]);

        Scheduler::publish_scheduled();

        $this->assertCount(0, $canal->sent);
    }

    public function test_publica_la_entrada_mas_antigua_primero(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $this->conFilas([
            (object) ['post_id' => 11, 'meta_value' => (string) (time() - 10)],
            (object) ['post_id' => 10, 'meta_value' => (string) (time() - 900)], // la más antigua
        ]);

        Scheduler::publish_scheduled();

        $this->assertCount(1, $canal->sent, 'sale una, no todas de golpe');
    }

    public function test_una_entrada_despublicada_se_descarta_sin_publicar(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $GLOBALS['_cp_test_status'][10] = 'draft';
        // La marca TIENE que existir antes: si no, borrarla o no borrarla da lo mismo y la
        // prueba no podría caer nunca (lo destapó la verificación en negativo).
        $GLOBALS['_cp_test_postmeta'][10]['_convoca_publisher_schedule_time'] = time() - 60;
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $this->assertCount(0, $canal->sent);
        $this->assertArrayNotHasKey('_convoca_publisher_schedule_time', $GLOBALS['_cp_test_postmeta'][10] ?? [], 'la marca de programación se limpia');
    }

    public function test_una_entrada_borrada_se_descarta(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $GLOBALS['_cp_test_missing'] = [10];
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $this->assertCount(0, $canal->sent);
    }

    public function test_sin_publicador_no_se_toca_nada(): void
    {
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $this->assertArrayNotHasKey('delete', array_flip($GLOBALS['_cp_test_db']['calls'] ?? []), 'sin publicador no se borra la marca: se reintentará');
    }

    public function test_si_publica_bien_se_limpia_la_marca_y_el_contador(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $this->assertCount(1, $canal->sent);
        $this->assertArrayNotHasKey('_convoca_publisher_schedule_time', $GLOBALS['_cp_test_postmeta'][10] ?? [], 'la marca de programación se borra');
        $this->assertArrayHasKey(Queue::LAST_SENT_OPTION, $GLOBALS['_cp_test_options'], 'y se apunta cuándo se envió');
    }

    public function test_si_falla_en_todas_no_se_borra_la_marca_y_se_reintenta(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook', false, 'la red dijo que no');
        $this->conPublisher($canal);
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $recolocada = $GLOBALS['_cp_test_postmeta'][10]['_convoca_publisher_schedule_time'] ?? null;
        $this->assertGreaterThan(time(), (int) $recolocada, 'se recoloca para más adelante en vez de perderse');
        $this->assertArrayNotHasKey(Queue::LAST_SENT_OPTION, $GLOBALS['_cp_test_options'], 'y no se marca como enviado');
    }

    public function test_agotados_los_intentos_se_pide_ayuda(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook', false, 'la red dijo que no');
        $this->conPublisher($canal);
        $GLOBALS['_cp_test_options']['admin_email'] = 'admin@example.com';
        // El contador de intentos vive en el meta de la entrada.
        $GLOBALS['_cp_test_postmeta'][10][Queue::ATTEMPTS_META] = Queue::MAX_ATTEMPTS;
        $this->conFilas([(object) ['post_id' => 10, 'meta_value' => (string) (time() - 60)]]);

        Scheduler::publish_scheduled();

        $this->assertNotEmpty($GLOBALS['_cp_test_mail'] ?? [], 'al agotar los intentos hay que avisar a alguien');
    }

    // ── reintento de fallos ────────────────────────────────────────────────────────────────

    public function test_sin_registro_de_fallos_no_reintenta_nada(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);

        Scheduler::retry_failed();

        $this->assertCount(0, $canal->sent);
    }

    public function test_reintenta_solo_en_las_cuentas_que_fallaron(): void
    {
        $fb = new FakeChannel('fb', 'facebook', 'Facebook');
        $tg = new FakeChannel('tg', 'telegram', 'Telegram');
        Publisher::init(['fb' => $fb, 'tg' => $tg]);

        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            ['post_id' => 10, 'success' => false, 'channel' => 'tg', 'time' => '', 'response' => ''],
        ];
        // El último resultado dice: en fb salió, en tg no.
        $GLOBALS['_cp_test_postmeta'][10]['_convoca_publisher_publish_results'] = [
            'fb' => ['success' => true],
            'tg' => ['success' => false, 'error' => 'chat not found'],
        ];

        Scheduler::retry_failed();

        $this->assertCount(0, $fb->sent, 'donde ya salió no se repite');
        $this->assertCount(1, $tg->sent, 'donde falló se reintenta');
    }

    public function test_un_fallo_de_una_entrada_que_ya_no_se_publica_no_se_reintenta(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $GLOBALS['_cp_test_status'][10] = 'draft';
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            ['post_id' => 10, 'success' => false, 'channel' => 'fb', 'time' => '', 'response' => ''],
        ];

        Scheduler::retry_failed();

        $this->assertCount(0, $canal->sent);
    }

    public function test_al_reintentar_agotado_pide_ayuda(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->conPublisher($canal);
        $GLOBALS['_cp_test_options']['admin_email'] = 'admin@example.com';
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            ['post_id' => 10, 'success' => false, 'channel' => 'fb', 'time' => '', 'response' => ''],
        ];
        $GLOBALS['_cp_test_postmeta'][10][Queue::ATTEMPTS_META] = Queue::MAX_ATTEMPTS;

        Scheduler::retry_failed();

        $this->assertNotEmpty($GLOBALS['_cp_test_mail'] ?? [], 'si ya no queda margen, se avisa');
    }
}
