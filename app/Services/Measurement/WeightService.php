<?php

namespace App\Services\Measurement;

use App\Models\Entities\WeightClass;
use App\Repositories\Interfaces\WeightClassRepositoryInterface;
use Illuminate\Support\Collection;

class WeightService
{
    private ?Collection $allMemo = null;

    public function __construct(protected WeightClassRepositoryInterface $weightClassRepo)
    {
    }

    public function allWeightClass(): Collection
    {
        if ($this->allMemo !== null) {
            return $this->allMemo;
        }

        $language = $this->resolveLanguage();

        return $this->allMemo = $this->weightClassRepo->listAllCached()
            ->mapWithKeys(function (WeightClass $item) use ($language) {
                $description = $item->descriptions->firstWhere('language_code', $language);

                return [
                    (int) $item->id => [
                        'weight_class_id' => (int) $item->id,
                        'title'           => (string) ($description?->title ?? ''),
                        'unit'            => (string) ($description?->unit ?? ''),
                        'value'           => (float) $item->value,
                    ],
                ];
            });
    }

    public function systemWeightClassId(): int
    {
        return (int) getConfigDb('config_weight_class_id');
    }

    public function convert(float $value, int $fromId, int $toId): float
    {
        if ($fromId === $toId) {
            return $value;
        }

        $classes = $this->allWeightClass();
        $fromValue = (float) ($classes[$fromId]['value'] ?? 0);
        $toValue = (float) ($classes[$toId]['value'] ?? 0);

        if ($fromValue <= 0 || $toValue <= 0) {
            return $value;
        }

        return $value * ($toValue / $fromValue);
    }

    public function convertToSystem(float $value, int $fromId): float
    {
        return $this->convert($value, $fromId, $this->systemWeightClassId());
    }

    public function format(float $value, int $weightClassId, int $decimal = 0, string $decimalPoint = '.', string $thousandPoint = ','): string
    {
        return number_format($value, $decimal, $decimalPoint, $thousandPoint) . $this->getUnit($weightClassId);
    }

    public function getUnit(int $weightClassId): string
    {
        return (string) ($this->allWeightClass()[$weightClassId]['unit'] ?? '');
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
