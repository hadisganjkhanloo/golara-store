<?php

namespace Api;

use JsonResponse;
use Product;
use Request;
use Review;

/** POST /api/v1/products/{id}/reviews {rating, comment?} — ثبت نظر (فقط کاربر واردشده) */
class ReviewController extends BaseController
{
    public function store(Request $request, array $params): void
    {
        $productId = (int) $params['id'];
        $customerId = (int) $_SESSION['customer_id'];
        $rating = (int) $request->input('rating', 0);
        $comment = trim((string) $request->input('comment', ''));

        if ((new Product())->find($productId) === null) {
            JsonResponse::error('محصول پیدا نشد.', 404);
        }

        if ($rating < 1 || $rating > 5) {
            JsonResponse::error('لطفاً یک امتیاز بین ۱ تا ۵ ستاره انتخاب کنید.', 422, ['rating' => 'امتیاز باید بین ۱ تا ۵ باشد.']);
        }

        $reviewModel = new Review();

        // قید UNIQUE دیتابیس جلوی نظر تکراری رو می‌گیره، حتی اگه دو تب همزمان بزنن
        if ($reviewModel->hasReviewed($productId, $customerId) || $reviewModel->create($productId, $customerId, $rating, $comment) === false) {
            JsonResponse::error('شما قبلاً برای این محصول نظر ثبت کرده‌اید.', 409);
        }

        ob_start();
        render_review_item([
            'customer_name' => $_SESSION['customer_name'],
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i'),
        ]);
        $html = ob_get_clean();

        $stats = $reviewModel->getAverageForProduct($productId);

        JsonResponse::success([
            'html' => $html,
            'average' => $stats['average'],
            'count' => $stats['count'],
        ], 'نظر شما ثبت شد. ممنون 🌸', 201);
    }
}
