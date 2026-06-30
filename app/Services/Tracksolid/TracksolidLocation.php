<?php

namespace App\Services\Tracksolid;

/**
 * Lightweight value object over a raw Tracksolid / JIMI location payload
 * (`jimi.device.location.get`).
 *
 * @phpstan-type RawLocation array<string, mixed>
 */
class TracksolidLocation
{
    /**
     * @param  RawLocation  $raw
     */
    public function __construct(public readonly array $raw) {}

    /**
     * @param  RawLocation  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self($raw);
    }

    public function imei(): string
    {
        return (string) ($this->raw['imei'] ?? '');
    }

    public function lat(): ?float
    {
        $value = $this->raw['lat'] ?? $this->raw['latGps'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    public function lng(): ?float
    {
        $value = $this->raw['lng'] ?? $this->raw['lon'] ?? $this->raw['lngGps'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Whether the device reported a usable GPS coordinate.
     */
    public function tienePosicion(): bool
    {
        return $this->lat() !== null && $this->lng() !== null;
    }

    public function velocidad(): int
    {
        $value = $this->raw['speed'] ?? 0;

        return is_numeric($value) ? (int) round((float) $value) : 0;
    }

    /**
     * Heading in degrees (0-359).
     */
    public function rumbo(): int
    {
        $value = $this->raw['direction'] ?? $this->raw['course'] ?? 0;

        return is_numeric($value) ? (int) round((float) $value) % 360 : 0;
    }

    /**
     * Whether the ignition (ACC) is on.
     */
    public function encendido(): bool
    {
        return (string) ($this->raw['accStatus'] ?? '0') === '1';
    }

    /**
     * Timestamp of the last GPS fix (device-local string from the API).
     */
    public function fechaGps(): ?string
    {
        $value = $this->raw['gpsTime'] ?? $this->raw['posTime'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Timestamp of the last heartbeat / communication.
     */
    public function fechaReporte(): ?string
    {
        $value = $this->raw['hbTime'] ?? $this->raw['gpsTime'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Movement state derived from ignition and speed.
     */
    public function estado(): string
    {
        if (! $this->encendido()) {
            return 'apagado';
        }

        return $this->velocidad() > 0 ? 'en_movimiento' : 'detenido';
    }

    /**
     * Compact, serializable shape for the Inertia frontend.
     *
     * @return array{imei: string, lat: float|null, lng: float|null, velocidad: int, rumbo: int, encendido: bool, estado: string, fecha_gps: string|null, fecha_reporte: string|null}
     */
    public function toFrontArray(): array
    {
        return [
            'imei' => $this->imei(),
            'lat' => $this->lat(),
            'lng' => $this->lng(),
            'velocidad' => $this->velocidad(),
            'rumbo' => $this->rumbo(),
            'encendido' => $this->encendido(),
            'estado' => $this->estado(),
            'fecha_gps' => $this->fechaGps(),
            'fecha_reporte' => $this->fechaReporte(),
        ];
    }
}
