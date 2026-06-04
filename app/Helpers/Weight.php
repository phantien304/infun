<?php

namespace App\Helpers;

use App\Model\Entities\WeightClass;

class Weight
{
    private $_weights = [];

    public function __construct()
    {
        $language = app()->getLocale();
        $area = getCurrentArea();
        if ($area == 'cms' || $area == 'batch') {
            $language = getConfigDb('config_language_admin');
        }
        $weightClass = WeightClass::with([
            'weightClassDescription' => function ($q) use ($language) {
                $q->where('language_code', $language);
            }
        ])->get();
        foreach ($weightClass as $item) {
            $this->_weights[$item->id] = [
                'weight_class_id' => $item->id,
                'title' => $item->weightClassDescription->title,
                'unit' => $item->weightClassDescription->unit,
                'value' => $item->value
            ];
        }
    }

    public function convert($value, $from, $to)
    {
        if ($from == $to) {
            return $value;
        }

        if (isset($this->_weights[$from])) {
            $from = $this->_weights[$from]['value'];
        } else {
            $from = 0;
        }

        if (isset($this->_weights[$to])) {
            $to = $this->_weights[$to]['value'];
        } else {
            $to = 0;
        }

        return $value * ($to / $from);
    }

    public function format($value, $weightClassId, $decimal = 0, $decimalPoint = '.', $thousandPoint = ',')
    {
        if (isset($this->_weights[$weightClassId])) {
            return number_format($value, $decimal, $decimalPoint, $thousandPoint) . $this->_weights[$weightClassId]['unit'];
        } else {
            return number_format($value, $decimal, $decimalPoint, $thousandPoint);
        }
    }

    public function getUnit($weightClassId)
    {
        if (isset($this->_weights[$weightClassId])) {
            return $this->_weights[$weightClassId]['unit'];
        } else {
            return '';
        }
    }
}
