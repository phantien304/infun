<?php

namespace App\Http\Controllers\Client\InfunStudio;

use App\Http\Controllers\Controller;
use App\Model\Entities\Product;
use App\Repositories\Client\InfunStudio\ReviewRepository;
use App\Repositories\Cms\ProductRepository;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function __construct(
        ReviewRepository $reviewRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        $this->setRepository($reviewRepository);
        $this->registerRepository($productRepository);
    }

    public function saveReview()
    {
        $ip = getIpVisitor();
        request()->merge(['ip' => $ip]);
        $params = $this->getParams();
        $this->getRepository()->getValidator();
        $valid = $this->getRepository()->getValidator()->validateCreate($params);
        if (!$valid) {
            return errValidator($this->getRepository()->getValidator()->errors(), 200);
        }

        $productId = array_get($params, 'product_id');
        $product = Product::where('id', $productId)->where('is_review', 1)->first();
        if (empty($product)) {
            return errNoValidator(trans('messages.ErrorAction'));
        }

        DB::beginTransaction();
        try {
            $this->getRepository()->create([
                'product_id' => $productId,
                'user_id' => getUserLoginId(),
                'ip' => $ip,
                'author' => array_get($params, 'author'),
                'text' => array_get($params, 'text'),
                'rating' => array_get($params, 'rating'),
                'email' => array_get($params, 'email'),
                'is_publish' => 0
            ]);
            DB::commit();
            return successData(trans('messages.ReviewSuccess'));
        } catch (\Exception $e) {
            logError($e->getMessage());
            DB::rollback();
        }
        return errNoValidator(trans('messages.ErrorAction'));
    }
}
