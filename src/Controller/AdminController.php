<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Voter\ActivityLogVoter;
use App\Security\Voter\ArticleVoter;
use App\Security\Voter\CategoryVoter;
use App\Security\Voter\FaqVoter;
use App\Security\Voter\InvoiceVoter;
use App\Security\Voter\MediaVoter;
use App\Security\Voter\NotificationVoter;
use App\Security\Voter\OrderVoter;
use App\Security\Voter\ProductCategoryVoter;
use App\Security\Voter\ProductVoter;
use App\Security\Voter\PromoCodeVoter;
use App\Security\Voter\ReviewVoter;
use App\Security\Voter\SettingVoter;
use App\Security\Voter\ShippingVoter;
use App\Security\Voter\StaticPageVoter;
use App\Security\Voter\StatisticsVoter;
use App\Security\Voter\SupportVoter;
use App\Security\Voter\UserVoter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    #[Route('/admin/{path}', name: 'app_admin_path', requirements: ['path' => '.+'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('admin/shell.html.twig', [
            'userEmail' => $user->getEmail(),
            'logoutUrl' => $this->generateUrl('app_security_logout'),
            'urls' => [
                'users'       => $this->generateUrl('api_admin_users_list'),
                'articles'    => $this->generateUrl('api_admin_articles_list'),
                'categories'        => $this->generateUrl('api_admin_categories_list'),
                'media'             => $this->generateUrl('api_admin_media_list'),
                'mediaUpload'       => $this->generateUrl('api_admin_media_upload'),
                'products'          => $this->generateUrl('api_admin_products_list'),
                'productCategories' => $this->generateUrl('api_admin_product_categories_list'),
                'orders'            => $this->generateUrl('api_admin_orders_list'),
                'shippingMethods'   => $this->generateUrl('api_admin_shipping_list'),
                'invoices'          => $this->generateUrl('api_admin_invoices_list'),
                'promoCodes'        => $this->generateUrl('api_admin_promo_list'),
                'settings'          => $this->generateUrl('api_admin_settings_get'),
                'statistics'        => $this->generateUrl('api_admin_stats_dashboard'),
                'faq'               => $this->generateUrl('api_admin_faq_list'),
                'support'           => $this->generateUrl('api_admin_support_list'),
                'pages'             => $this->generateUrl('api_admin_pages_list'),
                'notifications'     => $this->generateUrl('api_admin_notifications'),
                'reviews'           => $this->generateUrl('api_admin_reviews_list'),
                'activityLog'       => $this->generateUrl('api_admin_activity_log_list'),
            ],
            'permissions' => [
                'canViewUsers'          => $this->isGranted(UserVoter::VIEW),
                'canResetUserPassword'  => $this->isGranted(UserVoter::RESET_PASSWORD),
                'canVerifyUser'         => $this->isGranted(UserVoter::VERIFY),
                'canResendVerification' => $this->isGranted(UserVoter::RESEND_VERIFICATION),
                'canEditUserRoles'      => $this->isGranted(UserVoter::EDIT_ROLES),
                'canDeleteUser'         => $this->isGranted(UserVoter::DELETE),
                'canViewArticles'       => $this->isGranted(ArticleVoter::VIEW),
                'canCreateArticle'      => $this->isGranted(ArticleVoter::CREATE),
                'canEditArticle'        => $this->isGranted(ArticleVoter::EDIT),
                'canDeleteArticle'      => $this->isGranted(ArticleVoter::DELETE),
                'canPublishArticle'     => $this->isGranted(ArticleVoter::PUBLISH),
                'canViewCategories'     => $this->isGranted(CategoryVoter::VIEW),
                'canCreateCategory'     => $this->isGranted(CategoryVoter::CREATE),
                'canDeleteCategory'     => $this->isGranted(CategoryVoter::DELETE),
                'canViewOrders'         => $this->isGranted(OrderVoter::VIEW),
                'canEditOrder'          => $this->isGranted(OrderVoter::EDIT),
                'canDeleteOrder'        => $this->isGranted(OrderVoter::DELETE),
                'canViewProducts'       => $this->isGranted(ProductVoter::VIEW),
                'canCreateProduct'      => $this->isGranted(ProductVoter::CREATE),
                'canEditProduct'        => $this->isGranted(ProductVoter::EDIT),
                'canDeleteProduct'      => $this->isGranted(ProductVoter::DELETE),
                'canPublishProduct'     => $this->isGranted(ProductVoter::PUBLISH),
                'canViewProductCategories'   => $this->isGranted(ProductCategoryVoter::VIEW),
                'canCreateProductCategory'   => $this->isGranted(ProductCategoryVoter::CREATE),
                'canEditProductCategory'     => $this->isGranted(ProductCategoryVoter::EDIT),
                'canDeleteProductCategory'   => $this->isGranted(ProductCategoryVoter::DELETE),
                'canViewMedia'          => $this->isGranted(MediaVoter::VIEW),
                'canUploadMedia'        => $this->isGranted(MediaVoter::UPLOAD),
                'canDeleteMedia'        => $this->isGranted(MediaVoter::DELETE),
                'canViewSupport'        => $this->isGranted(SupportVoter::VIEW),
                'canReplySupport'       => $this->isGranted(SupportVoter::REPLY),
                'canEditSupport'        => $this->isGranted(SupportVoter::EDIT),
                'canViewReviews'        => $this->isGranted(ReviewVoter::VIEW),
                'canEditReview'         => $this->isGranted(ReviewVoter::EDIT),
                'canDeleteReview'       => $this->isGranted(ReviewVoter::DELETE),
                'canViewFaq'            => $this->isGranted(FaqVoter::VIEW),
                'canCreateFaq'          => $this->isGranted(FaqVoter::CREATE),
                'canEditFaq'            => $this->isGranted(FaqVoter::EDIT),
                'canDeleteFaq'          => $this->isGranted(FaqVoter::DELETE),
                'canViewPages'          => $this->isGranted(StaticPageVoter::VIEW),
                'canCreatePage'         => $this->isGranted(StaticPageVoter::CREATE),
                'canEditPage'           => $this->isGranted(StaticPageVoter::EDIT),
                'canDeletePage'         => $this->isGranted(StaticPageVoter::DELETE),
                'canViewPromoCodes'     => $this->isGranted(PromoCodeVoter::VIEW),
                'canCreatePromoCode'    => $this->isGranted(PromoCodeVoter::CREATE),
                'canEditPromoCode'      => $this->isGranted(PromoCodeVoter::EDIT),
                'canDeletePromoCode'    => $this->isGranted(PromoCodeVoter::DELETE),
                'canViewShipping'       => $this->isGranted(ShippingVoter::VIEW),
                'canCreateShipping'     => $this->isGranted(ShippingVoter::CREATE),
                'canEditShipping'       => $this->isGranted(ShippingVoter::EDIT),
                'canDeleteShipping'     => $this->isGranted(ShippingVoter::DELETE),
                'canViewInvoices'       => $this->isGranted(InvoiceVoter::VIEW),
                'canCreateInvoice'      => $this->isGranted(InvoiceVoter::CREATE),
                'canEditInvoice'        => $this->isGranted(InvoiceVoter::EDIT),
                'canDownloadInvoice'    => $this->isGranted(InvoiceVoter::DOWNLOAD),
                'canViewStatistics'     => $this->isGranted(StatisticsVoter::VIEW),
                'canViewNotifications'  => $this->isGranted(NotificationVoter::VIEW),
                'canViewSettings'       => $this->isGranted(SettingVoter::VIEW),
                'canEditSettings'       => $this->isGranted(SettingVoter::EDIT),
                'canViewActivityLog'    => $this->isGranted(ActivityLogVoter::VIEW),
            ],
        ]);
    }
}
