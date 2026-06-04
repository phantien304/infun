<?php

namespace App\Helpers;

use App\Model\Entities\Product;
use App\Model\Entities\ProductOption;
use App\Model\Entities\ProductOptionValue;
use App\Model\Entities\ProductOptionValue2;
use App\Model\Entities\ProductReward;
use App\Model\Entities\ProductSpecial;
use App\Helpers\Facades\Weight;
use App\Helpers\Facades\Length;
use Carbon\Carbon;

class Cart
{
    protected $_data = [];
    protected $_options = [];

    public function setOptions($options = [])
    {
        $this->_options = array_merge($this->_options, $options);
    }

    public function getOptions()
    {
        return $this->_options;
    }

    public function getProducts()
    {
        $carts = session()->get('cart', []);
        $now = Carbon::now();
        $weight = $width = $height = $length = 0;
        if (count($carts)) {
            foreach ($carts as $key => $item) {
                $options = array_get($item, 'option', []);
                $product = $this->_getProduct($item['id']);
                $quantity = array_get($item, 'quantity', 0);
                $stock = true;
                if ($product) {
                    $optionPrice = 0;
                    $optionPoints = 0;
                    $optionWeight = 0;
                    $optionData = [];
                    foreach ($options as $productOptionId => $optionValue) {
                        $option = $this->_getOption($productOptionId);
                        if ($option) {
                            if ($option['type'] == 'select' || $option['type'] == 'radio' || $option['type'] == 'image') {
                                $productOptionValue = $this->_getProductOptionValue($optionValue['product_option_value_id']);
                                $optionValue2Ids = $this->_processOptionValue2Ids(array_get($optionValue, 'children', []));
                                $productOptionValue2 = $this->_getProductOptionValue2($optionValue2Ids);

                                if (count($productOptionValue2)) {
                                    $childOptionValue2 = [];
                                    foreach ($productOptionValue2 as $optionValue2) {
                                        if ($optionValue2['price_prefix'] == '+') {
                                            $optionPrice += $optionValue2['price'];
                                        }
                                        if ($optionValue2['price_prefix'] == '-') {
                                            $optionPrice -= $optionValue2['price'];
                                        }
                                        if ($optionValue2['points_prefix'] == '+') {
                                            $optionPoints += $optionValue2['points'];
                                        }
                                        if ($optionValue2['points_prefix'] == '-') {
                                            $optionPoints -= $optionValue2['points'];
                                        }
                                        if ($optionValue2['weight_prefix'] == '+') {
                                            $optionWeight += $optionValue2['weight'];
                                        }
                                        if ($optionValue2['weight_prefix'] == '-') {
                                            $optionWeight -= $optionValue2['weight'];
                                        }
                                        if ($optionValue2->subtract && (empty($optionValue2->quantity) || $optionValue2->quantity < $quantity)) {
                                            $stock = false;
                                        }
                                        $childOptionValue2[] = [
                                            'id' => $optionValue2->id,
                                            'option_value_2_id' => $optionValue2->option_value_2_id,
                                            'name' => $optionValue2->name,
                                            'type' => $optionValue2->type,
                                            'variation' => $optionValue2->variation,
                                            'subtract' => $optionValue2->subtract,
                                            'value' => $optionValue2->value,
                                            'price' => $optionValue2->price,
                                            'price_prefix' => $optionValue2->price_prefix,
                                            'points' => $optionValue2->points,
                                            'points_prefix' => $optionValue2->points_prefix,
                                            'weight' => $optionValue2->weight,
                                            'weight_prefix' => $optionValue2->weight_prefix,
                                            'quantity' => $optionValue2->quantity,
                                        ];
                                    }

                                    $optionData[] = [
                                        'product_option_value_id' => $productOptionValue->id,
                                        'option_id' => $option->option_id,
                                        'product_option_id' => $productOptionId,
                                        'image' => $productOptionValue->image,
                                        'name' => $option->name,
                                        'type' => $option->type,
                                        'variation' => $option->variation,
                                        'required' => array_get($optionValue, 'required', 0),
                                        'value' => $productOptionValue->name,
                                        'child' => $childOptionValue2
                                    ];
                                }
                            }
                            if ($option['type'] == 'checkbox') {
                                foreach ($optionValue['product_option_value_id'] as $productOptionValueId) {
                                    $productOptionValue = $this->_getProductOptionValue($productOptionValueId);
                                    $optionValue2Ids = $this->_processOptionValue2Ids(array_get($optionValue, 'children.' . $productOptionValueId, []));
                                    $productOptionValue2 = $this->_getProductOptionValue2($optionValue2Ids);

                                    if (count($productOptionValue2)) {
                                        $childOptionValue2 = [];
                                        foreach ($productOptionValue2 as $optionValue2) {
                                            if ($optionValue2['price_prefix'] == '+') {
                                                $optionPrice += $optionValue2['price'];
                                            }
                                            if ($optionValue2['price_prefix'] == '-') {
                                                $optionPrice -= $optionValue2['price'];
                                            }
                                            if ($optionValue2['points_prefix'] == '+') {
                                                $optionPoints += $optionValue2['points'];
                                            }
                                            if ($optionValue2['points_prefix'] == '-') {
                                                $optionPoints -= $optionValue2['points'];
                                            }
                                            if ($optionValue2['weight_prefix'] == '+') {
                                                $optionWeight += $optionValue2['weight'];
                                            }
                                            if ($optionValue2['weight_prefix'] == '-') {
                                                $optionWeight -= $optionValue2['weight'];
                                            }
                                            if ($optionValue2->subtract && (empty($optionValue2->quantity) || $optionValue2->quantity < $quantity)) {
                                                $stock = false;
                                            }
                                            $childOptionValue2[] = [
                                                'id' => $optionValue2->id,
                                                'option_value_2_id' => $optionValue2->option_value_2_id,
                                                'name' => $optionValue2->name,
                                                'type' => $optionValue2->type,
                                                'variation' => $optionValue2->variation,
                                                'subtract' => $optionValue2->subtract,
                                                'value' => $optionValue2->value,
                                                'price' => $optionValue2->price,
                                                'price_prefix' => $optionValue2->price_prefix,
                                                'points' => $optionValue2->points,
                                                'points_prefix' => $optionValue2->points_prefix,
                                                'weight' => $optionValue2->weight,
                                                'weight_prefix' => $optionValue2->weight_prefix,
                                                'quantity' => $optionValue2->quantity,
                                            ];
                                        }

                                        $optionData[] = [
                                            'product_option_value_id' => $productOptionValue->id,
                                            'option_id' => $option->option_id,
                                            'product_option_id' => $productOptionId,
                                            'image' => $productOptionValue->image,
                                            'name' => $option->name,
                                            'type' => $option->type,
                                            'variation' => $option->variation,
                                            'required' => array_get($optionValue, 'required', 0),
                                            'value' => $productOptionValue->name,
                                            'child' => $childOptionValue2
                                        ];
                                    }
                                }
                            }
                            if ($option['type'] == 'text' || $option['type'] == 'textarea' || $option['type'] == 'email' || $option['type'] == 'phone'
                                || $option['type'] == 'file' || $option['type'] == 'date' || $option['type'] == 'datetime' || $option['type'] == 'time') {
                                $optionData[] = [
                                    'product_option_value_id' => '',
                                    'option_id' => $option->option_id,
                                    'product_option_id' => $productOptionId,
                                    'image' => '',
                                    'name' => $option->name,
                                    'type' => $option->type,
                                    'variation' => $option->variation,
                                    'required' => array_get($optionValue, 'required', 0),
                                    'value' => array_get($optionValue, 'value', ''),
                                    'child' => [],
                                    'quantity' => '',
                                    'subtract' => '',
                                    'price' => '',
                                    'price_prefix' => '',
                                    'points' => '',
                                    'points_prefix' => '',
                                    'weight' => '',
                                    'weight_prefix' => ''
                                ];
                            }
                        }
                    }

                    $userGroupId = auth()->check() ? auth()->user()->user_group_id : getConfigDb('config_user_group_id');
                    $this->setOptions([
                        'user_group_id' => $userGroupId
                    ]);

                    $price = $product->price;

                    $productSpecial = $this->_getProductSpecial($product->id, $now);

                    if ($productSpecial) {
                        $price = $productSpecial->price;
                    }

                    $reward = $this->_getProductReward($product->id);

                    if (!$product->quantity || ($product->quantity < $quantity)) {
                        $stock = false;
                    }

                    $this->_data[$key] = [
                        'key' => $key,
                        'id' => $product->id,
                        'name' => $product->name,
                        'model' => $product->model,
                        'shipping' => $product->shipping,
                        'image' => $product->image,
                        'option' => $optionData,
                        'quantity' => $quantity,
                        'minimum' => $product->minimum,
                        'subtract' => $product->subtract,
                        'stock' => $stock,
                        'price' => ($price + $optionPrice),
                        'total' => ($price + $optionPrice) * $quantity,
                        'reward' => $reward * $quantity,
                        'points' => ($product->points ? ($product->points + $optionPoints) * $quantity : 0),
                        'tax_class_id' => $product->tax_class_id,
                        'weight' => ($product->weight + $optionWeight) * $quantity,
                        'weight_class_id' => $product->weight_class_id,
                        'length' => $product->length,
                        'width' => $product->width,
                        'height' => $product->height,
                        'length_class_id' => $product->length_class_id,
                        'url' => $product->getUrlClient(),
                    ];
                    if ($product->shipping) {
                        if (filled($product->length_class_id)) {
                            $width = Length::convert($product->width, $product->length_class_id, getConfigDb('config_length_class_id'));
                            $height = Length::convert($product->height, $product->length_class_id, getConfigDb('config_length_class_id'));
                            $length = Length::convert($product->length, $product->length_class_id, getConfigDb('config_length_class_id'));
                        }
                        $weight += Weight::convert(($product->weight + $optionWeight) * $quantity, $product->weight_class_id, getConfigDb('config_weight_class_id'));
                    }
                } else {
                    $this->remove($key);
                }
            }
        }
        session()->put('cart_shipping', ['width' => $width, 'height' => $height, 'length' => $length, 'weight' => $weight]);
        return $this->_data;
    }

    protected function _getProduct($productId)
    {
        return Product::where('id', $productId)
            ->leftJoin('product_description', 'product_description.product_id', '=', 'product.id')
            ->dateAvailable()
            ->languageCode()
            ->first();
    }

    protected function _getOption($productOptionId)
    {
        return ProductOption::select('product_option.id', 'product_option.option_id', 'od.name', 'o.variation', 'o.type')
            ->where('product_option.id', $productOptionId)
            ->leftJoin('option AS o', 'product_option.option_id', '=', 'o.id')
            ->leftJoin('option_description AS od', 'od.option_id', '=', 'o.id')
            ->where('od.language_code', app()->getLocale())
            ->first();
    }

    protected function _getProductOptionValue($productOptionValueId)
    {
        return ProductOptionValue::select('product_option_value.id', 'ovd.name', 'product_option_value.image')
            ->leftJoin('option_value AS ov', 'ov.id', '=', 'product_option_value.option_value_1_id')
            ->leftJoin('option_value_description AS ovd', function ($q) {
                $q->on('ovd.option_value_id', '=', 'ov.id')
                    ->where('ovd.language_code', app()->getLocale());
            })
            ->where('product_option_value.id', $productOptionValueId)
            ->first();
    }

    protected function _getProductOptionValue2($optionValue2Ids)
    {
        return ProductOptionValue2::select('od.name', 'o.type', 'o.variation', 'ovd.name as value',
            'product_option_value_2.id', 'product_option_value_2.option_value_2_id', 'product_option_value_2.quantity',
            'product_option_value_2.subtract', 'product_option_value_2.price', 'product_option_value_2.price_prefix',
            'product_option_value_2.points', 'product_option_value_2.points_prefix', 'product_option_value_2.weight',
            'product_option_value_2.weight_prefix')
            ->leftJoin('option_value AS ov', 'ov.id', '=', 'product_option_value_2.option_value_2_id')
            ->leftJoin('option_value_description AS ovd', function ($q) {
                $q->on('ovd.option_value_id', '=', 'ov.id')
                    ->where('ovd.language_code', app()->getLocale());
            })
            ->leftJoin('option AS o', 'o.id', '=', 'ov.option_id')
            ->leftJoin('option_description AS od', function ($q) {
                $q->on('od.option_id', '=', 'o.id')
                    ->where('od.language_code', app()->getLocale());
            })
            ->whereIn('product_option_value_2.id', $optionValue2Ids)
            ->get();
    }

    protected function _processOptionValue2Ids($children = [])
    {
        $optionValue2Ids = [];
        if (count($children)) {
            foreach ($children as $child) {
                $child = json_decode($child, true);
                $optionValue2Ids[] = $child;
            }
        }
        return $optionValue2Ids;
    }

    protected function _getProductReward($productId)
    {
        $userGroupId = $this->getOptions('user_group_id');
        $productReward = ProductReward::where('product_id', $productId)
            ->where('user_group_id', $userGroupId)
            ->first();
        if ($productReward) {
            return $productReward->points;
        }
        return 0;
    }

    protected function _getProductSpecial($productId, $now)
    {
        $userGroupId = $this->getOptions('user_group_id');
        return ProductSpecial::where('product_id', $productId)
            ->where('user_group_id', $userGroupId)
            ->where(function ($qStart) use ($now) {
                $qStart->where('date_start', '<', $now)
                    ->orWhereNull('date_start');
            })
            ->where(function ($qEnd) use ($now) {
                $qEnd->where('date_end', '>', $now)
                    ->orWhereNull('date_end');
            })
            ->orderBy('priority', 'DESC')
            ->first();
    }

    public function add($data, $profileId = '')
    {
        $productId = array_get($data, 'id');
        $option = array_get($data, 'option', []);
        $quantity = (int)array_get($data, 'quantity', 0);

        $key = (int)$productId . ':';

        if (count($option)) {
            $key .= md5(serialize($option));
        }

        $key .= $productId;

        if ($quantity && ($quantity > 0)) {
            if (!session()->has('cart.' . $key)) {
                session()->put('cart.' . $key, $data);
            } else {
                session()->increment('cart.' . $key . '.quantity', $quantity);
            }
        }
    }

    public function update($key, $qty)
    {
        if ((int)$qty && ((int)$qty > 0)) {
            session()->put('cart.' . $key . '.quantity', (int)$qty);
        } else {
            session()->forget('cart.' . $key);
        }
    }

    public function remove($key)
    {
        if (session()->has('cart.' . $key)) {
            session()->forget('cart.' . $key);
        }
        $this->_data = [];
    }

    public function clear()
    {
        session()->forget('cart');
        session()->forget('total_cart_header');
        session()->forget('coupon');
        session()->forget('voucher');
        $this->_data = [];
    }

    public function getSubTotal()
    {
        $total = 0;

        foreach ($this->_data as $product) {
            $total += $product['total'];
        }

        return $total;
    }

    public function getTaxes()
    {
        $tax_data = array();

        foreach ($this->getProducts() as $product) {
            if ($product['tax_class_id']) {
                $tax_rates = $this->tax->getRates($product['price'], $product['tax_class_id']);

                foreach ($tax_rates as $tax_rate) {
                    if (!isset($tax_data[$tax_rate['tax_rate_id']])) {
                        $tax_data[$tax_rate['tax_rate_id']] = ($tax_rate['amount'] * $product['quantity']);
                    } else {
                        $tax_data[$tax_rate['tax_rate_id']] += ($tax_rate['amount'] * $product['quantity']);
                    }
                }
            }
        }

        return $tax_data;
    }

    public function getTotal()
    {
        $total = 0;

        foreach ($this->getProducts() as $product) {
            $total += $product['price'] * $product['quantity'];
        }

        return $total;
    }

    public function countProducts()
    {
        $productTotal = 0;

        $products = $this->getProducts();

        foreach ($products as $product) {
            $productTotal += $product['quantity'];
        }

        return $productTotal;
    }

    public function hasProducts()
    {
        return count(session()->get('cart', []));
    }

    public function hasStock()
    {
        $stock = true;

        foreach ($this->_data as $product) {
            if (!$product['stock']) {
                $stock = false;
            }
        }

        return $stock;
    }

    public function hasShipping()
    {
        $shipping = false;

        foreach ($this->getProducts() as $product) {
            if ($product['shipping']) {
                $shipping = true;

                break;
            }
        }

        return $shipping;
    }
}
