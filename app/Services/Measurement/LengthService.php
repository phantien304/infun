<?php

namespace App\Services\Measurement;

use App\Models\Entities\LengthClass;
use App\Repositories\Interfaces\LengthClassRepositoryInterface;
use Illuminate\Support\Collection;

class LengthService
{
    private ?Collection $allMemo = null;

    public function __construct(protected LengthClassRepositoryInterface $lengthClassRepo)
    {
    }

    public function allLengthClass(): Collection
    {
        if ($this->allMemo !== null) {
            return $this->allMemo;
        }

        $language = $this->resolveLanguage();

        return $this->allMemo = $this->lengthClassRepo->listAllCached()
            ->mapWithKeys(function (LengthClass $item) use ($language) {
                $description = $item->descriptions->firstWhere('language_code', $language);

                return [
                    (int) $item->id => [
                        'length_class_id' => (int) $item->id,
                        'title'           => (string) ($description?->title ?? ''),
                        'unit'            => (string) ($description?->unit ?? ''),
                        'value'           => (float) $item->value,
                    ],
                ];
            });
    }

    public function systemLengthClassId(): int
    {
        return (int) getConfigDb('config_length_class_id');
    }

    public function convert(float $value, int $fromId, int $toId): float
    {
        if ($fromId === $toId) {
            return $value;
        }

        $classes = $this->allLengthClass();
        $fromValue = (float) ($classes[$fromId]['value'] ?? 0);
        $toValue = (float) ($classes[$toId]['value'] ?? 0);

        if ($fromValue <= 0 || $toValue <= 0) {
            return $value;
        }

        return $value * ($toValue / $fromValue);
    }

    public function convertToSystem(float $value, int $fromId): float
    {
        return $this->convert($value, $fromId, $this->systemLengthClassId());
    }

    public function format(float $value, int $lengthClassId, string $decimalPoint = '.', string $thousandPoint = ','): string
    {
        return number_format($value, 2, $decimalPoint, $thousandPoint) . $this->getUnit($lengthClassId);
    }

    public function getUnit(int $lengthClassId): string
    {
        return (string) ($this->allLengthClass()[$lengthClassId]['unit'] ?? '');
    }

    protected function resolveLanguage(): string
    {
        $area = getCurrentArea();
        if ($area == 'cms' || $area == 'batch') {
            return (string) getConfigDb('config_language_admin');
        }

        return (string) app()->getLocale();
    }
}
