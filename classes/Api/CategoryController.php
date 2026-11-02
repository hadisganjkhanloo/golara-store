<?php

namespace Api;

use Category;
use JsonResponse;
use Request;

/**
 * API دسته‌بندی‌ها
 *   GET    /api/v1/categories          لیست (همراه با تعداد محصول)
 *   GET    /api/v1/categories/{id}     جزئیات
 *   POST   /api/v1/categories          ساخت   (نیاز به X-API-Key)
 *   PUT    /api/v1/categories/{id}     ویرایش (نیاز به X-API-Key)
 *   DELETE /api/v1/categories/{id}     حذف    (نیاز به X-API-Key؛ محصولاتش بدون دسته می‌شن)
 */
class CategoryController extends BaseController
{
    public function index(Request $request, array $params): void
    {
        $categories = array_map(fn($c) => [
            'id' => (int) $c['id'],
            'name' => $c['name'],
            'product_count' => (int) $c['product_count'],
        ], (new Category())->allWithProductCount());

        JsonResponse::success($categories);
    }

    public function show(Request $request, array $params): void
    {
        $category = (new Category())->find((int) $params['id']);

        if (!$category) {
            JsonResponse::error('دسته‌بندی پیدا نشد.', 404);
        }

        JsonResponse::success(['id' => (int) $category['id'], 'name' => $category['name']]);
    }

    public function store(Request $request, array $params): void
    {
        $name = $this->validName($request, 0);
        $id = (new Category())->create($name);

        JsonResponse::success(['id' => $id], 'دسته‌بندی ایجاد شد.', 201);
    }

    public function update(Request $request, array $params): void
    {
        $model = new Category();
        $id = (int) $params['id'];

        if (!$model->find($id)) {
            JsonResponse::error('دسته‌بندی پیدا نشد.', 404);
        }

        $model->rename($id, $this->validName($request, $id));

        JsonResponse::success(null, 'دسته‌بندی به‌روزرسانی شد.');
    }

    public function destroy(Request $request, array $params): void
    {
        $model = new Category();
        $id = (int) $params['id'];

        if (!$model->find($id)) {
            JsonResponse::error('دسته‌بندی پیدا نشد.', 404);
        }

        $model->delete($id);

        JsonResponse::success(null, 'دسته‌بندی حذف شد.');
    }

    private function validName(Request $request, int $exceptId): string
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '' || mb_strlen($name) > 100) {
            JsonResponse::error('اطلاعات ارسالی معتبر نیست.', 422, ['name' => 'نام دسته‌بندی الزامی است (حداکثر ۱۰۰ حرف).']);
        }
        if ((new Category())->nameExists($name, $exceptId)) {
            JsonResponse::error('اطلاعات ارسالی معتبر نیست.', 422, ['name' => 'دسته‌بندی دیگری با همین نام وجود دارد.']);
        }

        return $name;
    }
}
