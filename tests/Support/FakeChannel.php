<?php

/**
 * Canal de mentira para las pruebas.
 *
 * Un canal de verdad habla con una red; este solo necesita lo que el plugin le pregunta: de
 * qué red es, si está disponible y qué contesta al publicar (y dejar constancia de lo que se
 * le mandó). Estaba copiado —igual— en varios ficheros de pruebas: cuando la copia se
 * desvía, las pruebas empiezan a medir cosas distintas según dónde mires.
 */

namespace ConvocaPublisher\Tests\Support;

use ConvocaPublisher\Channels\ChannelInterface;

final class FakeChannel implements ChannelInterface
{
    /**
     * Lo que se le ha mandado, en orden: permite afirmar qué llegó y con qué texto.
     *
     * @var array<int, array{message: string, url: string}>
     */
    public array $sent = [];

    public function __construct(
        private readonly string $id,
        private readonly string $network,
        private readonly string $name = '',
        private readonly bool $works = true,
        private readonly string $error = 'la red dijo que no',
    ) {}

    public function get_id(): string
    {
        return $this->id;
    }

    /**
     * La red a la que pertenece (lo que en producción dice el envoltorio de cuenta).
     */
    public function get_channel_id(): string
    {
        return $this->network;
    }

    public function get_name(): string
    {
        return '' !== $this->name ? $this->name : $this->network;
    }

    public function is_available(): bool
    {
        // Lo que decide si una cuenta está lista es el canal de verdad y sus credenciales:
        // eso se prueba con las cuentas reales, no con este doble.
        return true;
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $this->sent[] = ['message' => $message, 'url' => $url];

        return $this->works
            ? ['success' => true, 'post_id' => 'ok']
            : ['success' => false, 'error' => $this->error];
    }

    public function get_settings_fields(): array
    {
        return [];
    }

    public function validate_settings(array $settings): array
    {
        return $settings;
    }

    public function verify_connection(): array
    {
        return ['success' => true];
    }
}
