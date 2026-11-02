<?php

namespace Api;

use JsonResponse;
use Product;
use Request;

/** GET /api/v1/search?q=...&page=2 — جستجوی زنده (کارت‌های آماده + لینک‌های صفحه‌بندی) */
class SearchController extends BaseController
{
    public const PER_PAGE = 8;

    public function index(Request $request, array $params): void
    {
        $query = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        if ($query === '') {
            JsonResponse::success(['html' => '', 'pagination' => '', 'total' => 0]);
        }

        // فقط حروف (فارسی/انگلیسی)، عدد و فاصله مجاز است
        if (!preg_match('/^[\p{L}\p{N}\s]+$/u', $query)) {
            JsonResponse::error('عبارت جستجو فقط می‌تواند شامل حروف، عدد و فاصله باشد.', 422, ['q' => 'کاراکتر غیرمجاز']);
        }

        $result = (new Product())->paginate(['q' => $query], $page, self::PER_PAGE);

        ob_start();
        if (empty($result['items'])) {
            echo '<p>نتیجه‌ای برای «' . clean_input($query) . '» یافت نشد.</p>';
        }
        foreach ($result['items'] as $product) {
            render_product_card($product);
        }
        $html = ob_get_clean();

        ob_start();
        render_pagination($result['page'], $result['pages'], url('search.php'), ['q' => $query]);
        $pagination = ob_get_clean();

        JsonResponse::success([
            'html' => $html,
            'pagination' => $pagination,
            'total' => $result['total'],
        ]);
    }
}
