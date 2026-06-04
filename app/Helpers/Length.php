<?php

namespace App\Helpers;

use App\Model\Entities\LengthClass;

class Length
{
    private $_lengths = [];

    public function __construct()
    {
        $language = app()->getLocale();
        $area = getCurrentArea();
        if ($area == 'cms' || $area == 'batch') {
            $language = getConfigDb('config_language_admin');
        }
        $lengthClass = LengthClass::with([
            'lengthClassDescription' => function ($q) use ($language) {
                $q->where('language_code', $language);
            }
        ])->get();
        foreach ($lengthClass as $item) {
            $this->_lengths[$item->id] = [
                'length_class_id' => $item->id,
                'title' => $item->lengthClassDescription->title,
                'unit' => $item->lengthClassDescription->unit,
                'value' => $item->value
            ];
        }
    }

    public function convert($value, $from, $to)
    {
        if ($from == $to) {
            return $value;
        }

        if (isset($this->_lengths[$from])) {
            $from = $this->_lengths[$from]['value'];
        } else {
            $from = 0;
        }

        if (isset($this->_lengths[$to])) {
            $to = $this->_lengths[$to]['value'];
        } else {
            $to = 0;
        }

        return $value * ($to / $from);
    }

    public function format($value, $lengthClassId, $decimalPoint = '.', $thousandPoint = ',')
    {
        if (isset($this->_lengths[$lengthClassId])) {
            return number_format($value, 2, $decimalPoint, $thousandPoint) . $this->_lengths[$lengthClassId]['unit'];
        } else {
            return number_format($value, 2, $decimalPoint, $thousandPoint);
        }
    }

    public function getUnit($lengthClassId)
    {
        if (isset($this->_lengths[$lengthClassId])) {
            return $this->_lengths[$lengthClassId]['unit'];
        } else {
            return '';
        }
    }
}
