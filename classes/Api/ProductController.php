<?php

namespace Api;

use Category;
use JsonResponse;
use Product;
use Request;

/**
 * API عمومی محصولات
 *   GET    /api/v1/products            لیست با فیلتر و صفحه‌بندی
 *   GET    /api/v1/products/{id}       جزئیات
 *   POST   /api/v1/products            ساخت   (نیاز به X-API-Key)
 *   PUT    /api/v1/products/{id}       ویرایش (نیاز به X-API-Key)
 *   DELETE /api/v1/products/{id}       حذف    (نیاز به X-API-Key)
 */
class ProductController extends BaseController
{
    private const SORTS = ['newest', 'oldest', 'price_asc', 'price_desc', 'name', 'bestseller'];

    public function index(Request $request, array $params): void
    {
        $sort = (string) $request->query('sort', 'newest');
        if (!in_array($sort, self::SORTS, true)) {
            JsonResponse::error('مقدار sort نامعتبر است.', 422, ['sort' => 'یکی از ' . implode(', ', self::SORTS)]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 12)));

        $result = (new Product())->paginate([
            'q' => $request->query('q', ''),
            'category_id' => $request->query('category_id', 0),
            'in_stock' => $request->query('in_stock', '') === '1',
            'min_price' => $request->query('min_price', ''),
            'max_price' => $request->query('max_price', ''),
            'sort' => $sort,
        ], $page, $perPage);

        JsonResponse::success([
            'items' => array_map([$this, 'productPayload'], $result['items']),
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'pages' => $result['pages'],
            ],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $product = (new Product())->find((int) $params['id']);

        if (!$product) {
            JsonResponse::error('محصولی با این شناسه پیدا نشد.', 404);
        }

        $category = $product['category_id'] ? (new Category())->find((int) $product['category_id']) : null;
        $product['category_name'] = $category['name'] ?? null;

        JsonResponse::success($this->productPayload($product));
    }

    public function store(Request $request, array $params): void
    {
        [$data, $errors] = $this->validated($request->json());

        if (!empty($errors)) {
            JsonResponse::error('اطلاعات ارسالی معتبر نیست.', 422, $errors);
        }

        $data['image'] = null;
        $id = (new Product())->create($data);

        JsonResponse::success(['id' => $id], 'محصول ایجاد شد.', 201);
    }

    public function update(Request $request, array $params): void
    {
        $model = new Product();
        $product = $model->find((int) $params['id']);

        if (!$product) {
            JsonResponse::error('محصولی با این شناسه پیدا نشد.', 404);
        }

        // فیلدهایی که نیومده باشن همون مقدار قبلی رو نگه می‌دارن (ویرایش جزئی)
        [$data, $errors] = $this->validated(array_merge([
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => $product['price'],
            'stock' => $product['stock'],
            'category_id' => $product['category_id'],
        ], $request->json()));

        if (!empty($errors)) {
            JsonResponse::error('اطلاعات ارسالی معتبر نیست.', 422, $errors);
        }

        $data['image'] = $product['image'];
        $model->update((int) $product['id'], $data);

        JsonResponse::success(null, 'محصول به‌روزرسانی شد.');
    }

    public function destroy(Request $request, array $params): void
    {
        $model = new Product();
        $id = (int) $params['id'];

        if (!$model->find($id)) {
            JsonResponse::error('محصولی با این شناسه پیدا نشد.', 404);
        }

        // حذف نرم: محصول از فروشگاه پنهان می‌شه ولی سفارش‌ها و آمار فروشش سر جاشون می‌مونن
        $model->softDelete($id);

        JsonResponse::success(null, 'محصول حذف شد.');
    }

    /**
     * @return array{0:array,1:array} [داده‌ی تمیزشده، خطاها]
     */
    private function validated(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'نام محصول الزامی است.';
        }

        $price = $this->intOrNull($input['price'] ?? null);
        if ($price === null) {
            $errors['price'] = 'قیمت باید عدد صحیح نامنفی باشد.';
        }

        $stock = $this->intOrNull($input['stock'] ?? 0);
        if ($stock === null) {
            $errors['stock'] = 'موجودی باید عدد صحیح نامنفی باشد.';
        }

        $categoryId = $input['category_id'] ?? null;
        if ($categoryId === '' || $categoryId === 0 || $categoryId === '0') {
            $categoryId = null;
        }
        if ($categoryId !== null) {
            $categoryId = $this->intOrNull($categoryId);
            if ($categoryId === null || (new Category())->find($categoryId) === null) {
                $errors['category_id'] = 'دسته‌بندی انتخاب‌شده وجود ندارد.';
            }
        }

        return [[
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'price' => $price,
            'stock' => $stock,
            'category_id' => $categoryId,
        ], $errors];
    }
}
