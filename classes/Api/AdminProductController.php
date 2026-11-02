<?php

namespace Api;

use Category;
use JsonResponse;
use Product;
use Request;

/** POST /api/v1/admin/products (multipart/form-data) — افزودن محصول از پنل مدیریت، همراه با آپلود عکس */
class AdminProductController extends BaseController
{
    public function store(Request $request, array $params): void
    {
        $errors = [];

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $errors['name'] = 'نام محصول را وارد کنید.';
        }

        $price = $this->intOrNull($request->input('price'));
        if ($price === null) {
            $errors['price'] = 'قیمت باید یک عدد صحیح باشد.';
        }

        $stock = $this->intOrNull($request->input('stock', 0));
        if ($stock === null) {
            $errors['stock'] = 'موجودی باید یک عدد صحیح (۰ یا بیشتر) باشد.';
        }

        $categoryId = (int) $request->input('category_id', 0);
        if ($categoryId > 0 && (new Category())->find($categoryId) === null) {
            $errors['category_id'] = 'دسته‌بندی انتخاب‌شده وجود ندارد.';
        }

        $image = null;
        $file = $request->file('image');
        if ($file && !empty($file['name'])) {
            $image = upload_product_image($file);
            if ($image === false) {
                $errors['image'] = 'فرمت تصویر مجاز نیست (فقط jpg، jpeg، png، webp).';
            }
        }

        if (!empty($errors)) {
            JsonResponse::error('اطلاعات فرم کامل یا معتبر نیست.', 422, $errors);
        }

        (new Product())->create([
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'name' => $name,
            'description' => trim((string) $request->input('description', '')),
            'price' => $price,
            'stock' => $stock,
            'image' => $image,
        ]);

        JsonResponse::success(null, 'محصول «' . $name . '» با موفقیت اضافه شد.', 201);
    }
}
