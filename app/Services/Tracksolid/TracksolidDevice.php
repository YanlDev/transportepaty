<?php

namespace App\Services\Tracksolid;

/**
 * Lightweight value object over a raw Tracksolid / JIMI device payload.
 *
 * The Open API returns slightly different key names between the device list
 * and the device detail endpoints, so the accessors look at the common
 * aliases and normalize them for the rest of the application.
 *
 * @phpstan-type RawDevice array<string, mixed>
 */
class TracksolidDevice
{
    /**
     * @param  RawDevice  $raw
     */
    public function __construct(public readonly array $raw) {}

    /**
     * @param  RawDevice  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self($raw);
    }

    public function imei(): string
    {
        return (string) ($this->raw['imei'] ?? $this->raw['deviceImei'] ?? '');
    }

    /**
     * Device model / hardware type (e.g. "JC181").
     */
    public function modelo(): ?string
    {
        return $this->firstFilled(['deviceModel', 'mcType', 'machineType', 'model']);
    }

    public function placa(): ?string
    {
        return $this->firstFilled(['vehicleNumber', 'plateNumber', 'carLicense']);
    }

    public function vin(): ?string
    {
        return $this->firstFilled(['carFrame', 'vin', 'frameNumber']);
    }

    public function marca(): ?string
    {
        return $this->firstFilled(['vehicleBrand', 'carBrand', 'brand']);
    }

    public function modeloVehiculo(): ?string
    {
        return $this->firstFilled(['vehicleModels', 'vehicleModel', 'carModel']);
    }

    public function numeroMotor(): ?string
    {
        return $this->firstFilled(['engineNumber', 'engineNo']);
    }

    public function conductor(): ?string
    {
        return $this->firstFilled(['driverName', 'driver']);
    }

    /**
     * Current odometer reading in kilometres, if reported.
     */
    public function kilometraje(): ?int
    {
        $value = $this->raw['currentMileage'] ?? $this->raw['totalMileage'] ?? $this->raw['mileage'] ?? null;

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * Whether the device is enabled / active on the platform.
     */
    public function activo(): bool
    {
        $flag = $this->raw['enabledFlag'] ?? $this->raw['activate'] ?? $this->raw['status'] ?? null;

        return (bool) $flag;
    }

    /**
     * Whether the hardware is a dashcam (supports live video). Used by the
     * camera module in a later phase.
     */
    public function esDashcam(): bool
    {
        $modelo = strtoupper((string) $this->modelo());

        return str_contains($modelo, 'JC')
            || str_contains($modelo, 'CAM')
            || str_contains($modelo, 'DASH');
    }

    /**
     * Atributos del dispositivo para rellenar un Vehiculo (sólo los que traen
     * valor). El kilometraje NO va aquí: el odómetro se maneja aparte con la
     * calibración (ver Vehiculo::aplicarLecturaGps()).
     *
     * @return array<string, string>
     */
    public function toVehiculoAttributes(): array
    {
        return array_filter([
            'placa' => $this->placa(),
            'marca' => $this->marca(),
            'modelo' => $this->modeloVehiculo(),
            'numero_serie' => $this->vin(),
            'numero_motor' => $this->numeroMotor(),
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * Compact, serializable shape for the Inertia frontend.
     *
     * @return array{imei: string, modelo: string|null, placa: string|null, vin: string|null, marca: string|null, modelo_vehiculo: string|null, conductor: string|null, kilometraje: int|null, activo: bool, es_dashcam: bool}
     */
    public function toFrontArray(): array
    {
        return [
            'imei' => $this->imei(),
            'modelo' => $this->modelo(),
            'placa' => $this->placa(),
            'vin' => $this->vin(),
            'marca' => $this->marca(),
            'modelo_vehiculo' => $this->modeloVehiculo(),
            'conductor' => $this->conductor(),
            'kilometraje' => $this->kilometraje(),
            'activo' => $this->activo(),
            'es_dashcam' => $this->esDashcam(),
        ];
    }

    /**
     * First non-empty string value among the given keys.
     *
     * @param  list<string>  $keys
     */
    private function firstFilled(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->raw[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
