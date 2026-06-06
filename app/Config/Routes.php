<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ---------------------------------------------------------
// PUBLIC CONTROLLERS
// ---------------------------------------------------------
use App\Controllers\Public\HomeController;
use App\Controllers\Public\LaboratoryController;
use App\Controllers\Public\BookingController;
use App\Controllers\Public\AssetBrowseController;
use App\Controllers\Public\DocumentController;
use App\Controllers\Public\ChatbotController;
use App\Controllers\Public\QrController;
use App\Controllers\Public\AppLinkController;
use App\Controllers\Api\NativeAuthController;
use App\Controllers\Api\NativeBootstrapController;
use App\Controllers\Api\NativeLaboratoryController;
use App\Controllers\Api\NativeBookingController;
use App\Controllers\Api\NativeNotificationController;
use App\Controllers\Api\NativeExternalRequestController;
use App\Controllers\Api\NativeExternalRequestReviewController;
use App\Controllers\Api\NativeReferenceController;
use App\Controllers\Api\NativeHealthController;
use App\Controllers\Api\NativeApprovalQueueController;
use App\Controllers\Api\NativePushController;
use App\Controllers\Api\NativeIssueReportController;
use App\Controllers\Api\NativeMaintenanceController;
use App\Controllers\Api\NativeProfileController;
use App\Controllers\Api\NativeReportController;
use App\Controllers\Api\NativeAdminSettingsController;
use App\Controllers\Api\NativeAdminUserController;
use App\Controllers\Api\NativeAdminLaboratoryController;
use App\Controllers\Api\NativeAdminAssetController;

// ---------------------------------------------------------
// DASHBOARD CONTROLLERS
// ---------------------------------------------------------
use App\Controllers\Dashboard\DashboardController;
use App\Controllers\Dashboard\StudentDashboard;
use App\Controllers\Dashboard\ExternalDashboard;
use App\Controllers\Dashboard\PicDashboard;
use App\Controllers\Dashboard\ManagerDashboard;
use App\Controllers\Dashboard\AdminDashboard;
use App\Controllers\Dashboard\TechnicianDashboard;
use App\Controllers\Dashboard\IssueReportController;
use App\Controllers\Dashboard\ApprovalsController;
use App\Controllers\Dashboard\ProfileController;
use App\Controllers\Dashboard\ReportController;
use App\Controllers\Dashboard\NotificationController;
use App\Controllers\Dashboard\EmailInboxController;
use App\Controllers\Dashboard\ExternalRequestsController;
use App\Controllers\Dashboard\PushSubscriptionController;

// ---------------------------------------------------------
// BOOKING APPROVAL CONTROLLER
// ---------------------------------------------------------
use App\Controllers\Approvals\BookingApprovalController;

// ---------------------------------------------------------
// ADMIN CONTROLLERS
// ---------------------------------------------------------
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\AssetController;
use App\Controllers\Admin\LaboratoryAdminController;
use App\Controllers\Admin\UserManagementController;
use App\Controllers\Auth\PasswordRecoveryController;
use App\Controllers\Technician\MaintenanceController;


// ====================================================================
// PUBLIC ROUTES (NO LOGIN REQUIRED)
// ====================================================================

$routes->get('/', [HomeController::class, 'index']);
$routes->get('/contact', [HomeController::class, 'contact']);
$routes->get('open/booking/(:num)', [AppLinkController::class, 'booking/$1']);

// ====================================================================
// NATIVE APP API ROUTES
// ====================================================================

$routes->group('api/native', static function ($routes) {
    $routes->get('health', [NativeHealthController::class, 'show']);
    $routes->post('auth/token', [NativeAuthController::class, 'token']);
    $routes->post('auth/otp/verify', [NativeAuthController::class, 'verifyOtp']);
    $routes->post('auth/register', [NativeAuthController::class, 'register']);

    $routes->get('labs', [NativeLaboratoryController::class, 'index']);
    $routes->get('labs/(:num)', [NativeLaboratoryController::class, 'show/$1']);
    $routes->get('labs/(:num)/calendar', [NativeBookingController::class, 'calendarWithAssets/$1']);
    $routes->get('labs/(:num)/day/(:segment)', [NativeBookingController::class, 'dayWithAssets/$1/$2']);
    $routes->get('labs/(:num)/recommended-slots', [NativeBookingController::class, 'recommendedSlots/$1']);
    $routes->get('references/faculties', [NativeReferenceController::class, 'faculties']);
    $routes->post('bookings/check-slot', [NativeBookingController::class, 'checkSlot']);
});

$routes->group('api/native', ['filter' => 'tokens'], static function ($routes) {
    $routes->get('auth/me', [NativeAuthController::class, 'me']);
    $routes->post('auth/logout', [NativeAuthController::class, 'logout']);
    $routes->get('bootstrap', [NativeBootstrapController::class, 'show']);
    $routes->get('profile', [NativeProfileController::class, 'show']);
    $routes->post('profile', [NativeProfileController::class, 'update']);
    $routes->post('profile/twofa', [NativeProfileController::class, 'toggleTwofa']);
    $routes->get('push', [NativePushController::class, 'show']);
    $routes->post('push/register', [NativePushController::class, 'register']);
    $routes->post('push/unregister', [NativePushController::class, 'unregister']);
    $routes->get('reports', [NativeReportController::class, 'show']);
    $routes->get('reports/export/pdf', [NativeReportController::class, 'downloadPdf']);
    $routes->get('reports/export/csv', [NativeReportController::class, 'downloadCsv']);

    $routes->get('bookings', [NativeBookingController::class, 'index']);
    $routes->get('bookings/(:num)', [NativeBookingController::class, 'show/$1']);
    $routes->post('bookings/submit', [NativeBookingController::class, 'submit']);
    $routes->post('bookings/(:num)/cancel', [NativeBookingController::class, 'cancel/$1']);

    $routes->get('issues', [NativeIssueReportController::class, 'index']);
    $routes->post('issues', [NativeIssueReportController::class, 'store']);

    $routes->get('notifications', [NativeNotificationController::class, 'index']);
    $routes->post('notifications/read-all', [NativeNotificationController::class, 'markAllRead']);
    $routes->post('notifications/(:num)/read', [NativeNotificationController::class, 'markRead/$1']);
    $routes->post('notifications/(:num)/unread', [NativeNotificationController::class, 'markUnread/$1']);

    $routes->get('approvals/queue', [NativeApprovalQueueController::class, 'index']);
    $routes->get('approvals/queue/(:num)', [NativeApprovalQueueController::class, 'show/$1']);
    $routes->post('approvals/queue/(:num)/approve', [BookingApprovalController::class, 'approve/$1']);
    $routes->post('approvals/queue/(:num)/reject', [BookingApprovalController::class, 'reject/$1']);

    $routes->get('external-requests', [NativeExternalRequestController::class, 'index']);
    $routes->get('external-requests/(:num)', [NativeExternalRequestController::class, 'show/$1']);
    $routes->get('external-requests/labs/(:num)/slots/(:segment)', [NativeExternalRequestController::class, 'daySlots/$1/$2']);
    $routes->post('external-requests', [NativeExternalRequestController::class, 'store']);
    $routes->post('external-requests/(:num)', [NativeExternalRequestController::class, 'update/$1']);
    $routes->get('external-requests/review', [NativeExternalRequestReviewController::class, 'index']);
    $routes->get('external-requests/review/(:num)', [NativeExternalRequestReviewController::class, 'show/$1']);
    $routes->post('external-requests/review/(:num)/status', [NativeExternalRequestReviewController::class, 'updateStatus/$1']);

    $routes->get('maintenance', [NativeMaintenanceController::class, 'index']);
    $routes->get('maintenance/(:num)', [NativeMaintenanceController::class, 'show/$1']);
    $routes->post('maintenance', [NativeMaintenanceController::class, 'store']);
    $routes->post('maintenance/(:num)', [NativeMaintenanceController::class, 'update/$1']);

    $routes->get('documents/pdf/(:segment)', [DocumentController::class, 'viewPdf/$1']);
    $routes->get('admin/settings', [NativeAdminSettingsController::class, 'show']);
    $routes->post('admin/settings', [NativeAdminSettingsController::class, 'update']);
    $routes->post('admin/settings/slots', [NativeAdminSettingsController::class, 'saveSlots']);
    $routes->post('admin/settings/run-scheduled-tasks', [NativeAdminSettingsController::class, 'runScheduledTasks']);
    $routes->get('admin/users', [NativeAdminUserController::class, 'index']);
    $routes->get('admin/users/(:num)', [NativeAdminUserController::class, 'show/$1']);
    $routes->post('admin/users', [NativeAdminUserController::class, 'store']);
    $routes->post('admin/users/(:num)', [NativeAdminUserController::class, 'update/$1']);
    $routes->post('admin/users/(:num)/send-recovery', [NativeAdminUserController::class, 'sendRecovery/$1']);
    $routes->post('admin/users/(:num)/delete', [NativeAdminUserController::class, 'delete/$1']);
    $routes->get('admin/labs', [NativeAdminLaboratoryController::class, 'index']);
    $routes->get('admin/labs/(:num)', [NativeAdminLaboratoryController::class, 'show/$1']);
    $routes->post('admin/labs', [NativeAdminLaboratoryController::class, 'store']);
    $routes->post('admin/labs/(:num)', [NativeAdminLaboratoryController::class, 'update/$1']);
    $routes->post('admin/labs/(:num)/delete', [NativeAdminLaboratoryController::class, 'delete/$1']);
    $routes->get('admin/assets', [NativeAdminAssetController::class, 'index']);
    $routes->get('admin/assets/(:num)', [NativeAdminAssetController::class, 'show/$1']);
    $routes->post('admin/assets', [NativeAdminAssetController::class, 'store']);
    $routes->post('admin/assets/(:num)', [NativeAdminAssetController::class, 'update/$1']);
    $routes->post('admin/assets/(:num)/delete', [NativeAdminAssetController::class, 'delete/$1']);
});

// Laboratories
$routes->get('/laboratories', [LaboratoryController::class, 'index']);
$routes->get('/laboratories/(:num)', [LaboratoryController::class, 'show/$1']);

// Booking APIs
$routes->get('/api/calendar-with-assets/(:num)', [BookingController::class, 'calendarWithAssets/$1']);
$routes->get('/api/bookings/day-with-assets/(:num)/(:segment)', [BookingController::class, 'dayWithAssets/$1/$2']);

// Booking operations
$routes->post('/api/bookings/check-slot', [BookingController::class, 'checkSlot']);
$routes->post('/api/bookings/submit', [BookingController::class, 'submit']);
// Chatbot insights (role-aware, local)
$routes->post('/api/chat', [ChatbotController::class, 'respond']);

// Assets browsing
$routes->get('assets', [AssetBrowseController::class, 'index']);
$routes->get('qr/asset/(:segment)', [QrController::class, 'asset/$1']);

// PDF Document viewing (with authentication)
$routes->get('document/pdf/(:segment)', [DocumentController::class, 'viewPdf/$1'], ['filter' => 'session']);

// ====================================================================
// FILE ACCESS ROUTES (FOR UPLOADED FILES)
// ====================================================================

// Backward-compatible PDF route. Actual authorization and file serving happen in DocumentController.
$routes->get('uploads/pdfs/(:segment)', function($filename) {
    $filename = basename((string) $filename);
    if (! preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $filename)) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid filename');
    }

    return redirect()->to('/document/pdf/' . $filename);
}, ['filter' => 'session']);

// ====================================================================
// NEW IMAGE ROUTES (for images stored in public/images directory)
// ====================================================================

// Route to serve lab images from NEW location: public/images/labs/
$routes->get('images/labs/(:any)', function($filename) {
    // Security: only allow alphanumeric, dots, underscores, and hyphens
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', (string)$filename)) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid filename');
    }
    
    $filepath = FCPATH . 'images/labs/' . $filename;
    
    if (!file_exists($filepath) || !is_file($filepath)) {
        // If file doesn't exist, serve a placeholder
        $placeholderPath = FCPATH . 'images/labs/placeholder_lab.jpg';
        if (file_exists($placeholderPath)) {
            $filepath = $placeholderPath;
        } else {
            // Create a simple placeholder on the fly
            header("Content-Type: image/svg+xml");
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
                    <rect width="400" height="300" fill="#e0f2fe"/>
                    <text x="200" y="150" font-family="Arial" font-size="24" fill="#3b82f6" text-anchor="middle" dy=".3em">Lab Image</text>
                  </svg>';
            exit();
        }
    }
    
    $mime = mime_content_type($filepath);
    if (strpos($mime, 'image/') !== 0) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid file type');
    }
    
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($filepath));
    header("Cache-Control: public, max-age=86400");
    
    readfile($filepath);
    exit();
});

// Route to serve PIC images from NEW location: public/images/pic/
$routes->get('images/pic/(:any)', function($filename) {
    // Security: only allow alphanumeric, dots, underscores, and hyphens
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', (string)$filename)) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid filename');
    }
    
    $filepath = FCPATH . 'images/pic/' . $filename;
    
    if (!file_exists($filepath) || !is_file($filepath)) {
        // If file doesn't exist, serve a placeholder
        $placeholderPath = FCPATH . 'images/pic/placeholder_pic.png';
        if (file_exists($placeholderPath)) {
            $filepath = $placeholderPath;
        } else {
            // Create a simple placeholder on the fly
            header("Content-Type: image/svg+xml");
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
                    <circle cx="100" cy="100" r="80" fill="#3b82f6"/>
                    <circle cx="100" cy="80" r="40" fill="#ffffff"/>
                    <ellipse cx="100" cy="140" rx="50" ry="40" fill="#ffffff"/>
                  </svg>';
            exit();
        }
    }
    
    $mime = mime_content_type($filepath);
    if (strpos($mime, 'image/') !== 0) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid file type');
    }
    
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($filepath));
    header("Cache-Control: public, max-age=86400");
    
    readfile($filepath);
    exit();
});

// Route to serve asset images from NEW location: public/images/assets/
$routes->get('images/assets/(:any)', function($filename) {
    // Security: only allow alphanumeric, dots, underscores, and hyphens
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', (string)$filename)) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid filename');
    }
    
    $filepath = FCPATH . 'images/assets/' . $filename;
    
    if (!file_exists($filepath) || !is_file($filepath)) {
        // If file doesn't exist, serve a placeholder
        $placeholderPath = FCPATH . 'images/assets/placeholder_asset.png';
        if (file_exists($placeholderPath)) {
            $filepath = $placeholderPath;
        } else {
            // Create a simple placeholder on the fly
            header("Content-Type: image/svg+xml");
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
                    <rect width="400" height="300" fill="#f0f9ff"/>
                    <text x="200" y="150" font-family="Arial" font-size="24" fill="#1e40af" text-anchor="middle" dy=".3em">Equipment Image</text>
                  </svg>';
            exit();
        }
    }
    
    $mime = mime_content_type($filepath);
    if (strpos($mime, 'image/') !== 0) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid file type');
    }
    
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($filepath));
    header("Cache-Control: public, max-age=86400");
    
    readfile($filepath);
    exit();
});

// ====================================================================
// OLD IMAGE ROUTES (keep for backward compatibility during transition)
// ====================================================================

$safeUploadedImage = static function (
    string $filename,
    string $oldDirectory,
    string $newDirectory = '',
    ?string $placeholderPath = null,
    string $placeholderSvg = ''
): void {
    $filename = basename($filename);
    if ($filename === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid filename');
    }

    $candidates = [];
    $oldBase = realpath(WRITEPATH . trim($oldDirectory, '/'));
    if ($oldBase) {
        $candidates[] = [$oldBase, realpath($oldBase . DIRECTORY_SEPARATOR . $filename)];
    }

    if ($newDirectory !== '') {
        $newBase = realpath(FCPATH . trim($newDirectory, '/'));
        if ($newBase) {
            $candidates[] = [$newBase, realpath($newBase . DIRECTORY_SEPARATOR . $filename)];
        }
    }

    $filepath = null;
    foreach ($candidates as [$base, $candidate]) {
        if (
            $candidate
            && is_file($candidate)
            && str_starts_with($candidate, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            $filepath = $candidate;
            break;
        }
    }

    if (! $filepath && $placeholderPath && is_file($placeholderPath)) {
        $filepath = $placeholderPath;
    }

    if (! $filepath) {
        if ($placeholderSvg !== '') {
            header('Content-Type: image/svg+xml');
            echo $placeholderSvg;
            exit();
        }
        throw new \CodeIgniter\Exceptions\PageNotFoundException('File not found');
    }

    $mime = mime_content_type($filepath) ?: '';
    if (strpos($mime, 'image/') !== 0) {
        throw new \CodeIgniter\Exceptions\PageNotFoundException('Invalid file type');
    }

    header("Content-Type: $mime");
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: public, max-age=86400');

    readfile($filepath);
    exit();
};

// Route to serve uploaded lab images (OLD location - for backward compatibility)
$routes->get('uploads/labs/(:any)', function($filename) use ($safeUploadedImage) {
    $placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="#e0f2fe"/><text x="200" y="150" font-family="Arial" font-size="24" fill="#3b82f6" text-anchor="middle" dy=".3em">Lab Image</text></svg>';
    $safeUploadedImage($filename, 'uploads/labs', 'images/labs', FCPATH . 'images/labs/placeholder_lab.jpg', $placeholderSvg);
});

// Route to serve uploaded PIC images (OLD location - for backward compatibility)
$routes->get('uploads/pic/(:any)', function($filename) use ($safeUploadedImage) {
    $placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><circle cx="100" cy="100" r="80" fill="#3b82f6"/><circle cx="100" cy="80" r="40" fill="#ffffff"/><ellipse cx="100" cy="140" rx="50" ry="40" fill="#ffffff"/></svg>';
    $safeUploadedImage($filename, 'uploads/pic', 'images/pic', FCPATH . 'images/pic/placeholder_pic.png', $placeholderSvg);
});

// Route to serve uploaded asset images (OLD location - for backward compatibility)
$routes->get('uploads/assets/(:any)', function($filename) use ($safeUploadedImage) {
    $placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="#f0f9ff"/><text x="200" y="150" font-family="Arial" font-size="24" fill="#1e40af" text-anchor="middle" dy=".3em">Equipment Image</text></svg>';
    $safeUploadedImage($filename, 'uploads/assets', 'images/assets', FCPATH . 'images/assets/placeholder_asset.png', $placeholderSvg);
});

// Optional: Generic route for other uploaded images
$routes->get('uploads/images/(:any)', function($filename) use ($safeUploadedImage) {
    $safeUploadedImage($filename, 'uploads/images');
});

// ====================================================================
// MAIN DASHBOARD ENTRY
// ====================================================================

$routes->get('dashboard', [DashboardController::class, 'index'], ['filter' => 'session']);


// ====================================================================
// DASHBOARD ROUTES (LOGIN REQUIRED)
// ====================================================================

$routes->group('dashboard', ['filter' => 'session'], function ($routes) {
    $routes->get('profile', [ProfileController::class, 'index']);
    $routes->post('profile/update', [ProfileController::class, 'update']);
    $routes->get('reports/pdf', [ReportController::class, 'download'], ['filter' => 'group:pic,manager,admin']);
    $routes->get('reports/csv', [ReportController::class, 'downloadCsv'], ['filter' => 'group:pic,manager,admin']);
    $routes->get('notifications', [NotificationController::class, 'index']);
    $routes->post('notifications/read/(:num)', [NotificationController::class, 'markRead/$1']);
    $routes->post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    $routes->post('push/subscribe', [PushSubscriptionController::class, 'subscribe']);
    $routes->post('push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
    $routes->post('push/test', [PushSubscriptionController::class, 'test']);
    $routes->get('emails', [EmailInboxController::class, 'index']);
    $routes->get('emails/(:num)', [EmailInboxController::class, 'show/$1']);

    // STUDENT DASHBOARD
    $routes->get('student', [StudentDashboard::class, 'index'], ['filter' => 'group:student,staff']);
    $routes->get('student/booking-details/(:num)', [StudentDashboard::class, 'bookingDetails/$1'], ['filter' => 'group:student,staff']);
    $routes->post('student/cancel-booking/(:num)', [StudentDashboard::class, 'cancelBooking/$1'], ['filter' => 'group:student,staff']);

    // EXTERNAL DASHBOARD
    $routes->get('external', [ExternalDashboard::class, 'index'], ['filter' => 'group:external']);
    $routes->get('external/request', [ExternalDashboard::class, 'createRequest'], ['filter' => 'group:external']);
    $routes->get('external/request/slots/(:num)/(:segment)', [ExternalDashboard::class, 'daySlots/$1/$2'], ['filter' => 'group:external']);
    $routes->post('external/request/store', [ExternalDashboard::class, 'storeRequest'], ['filter' => 'group:external']);
    $routes->get('external/request/edit/(:num)', [ExternalDashboard::class, 'editRequest/$1'], ['filter' => 'group:external']);
    $routes->post('external/request/update/(:num)', [ExternalDashboard::class, 'updateRequest/$1'], ['filter' => 'group:external']);

    // PIC DASHBOARD
    $routes->get('pic', [PicDashboard::class, 'index'], ['filter' => 'group:pic']);
    $routes->get('pic/booking/(:num)', [PicDashboard::class, 'getBookingDetails/$1'], ['filter' => 'group:pic']);

    // MANAGER DASHBOARD
    $routes->get('manager', [ManagerDashboard::class, 'index'], ['filter' => 'group:manager']);
    $routes->get('manager/booking/(:num)', [ManagerDashboard::class, 'getBookingDetails/$1'], ['filter' => 'group:manager']);

    // ADMIN DASHBOARD
    $routes->get('admin', [AdminDashboard::class, 'index'], ['filter' => 'group:admin']);

    // TECHNICIAN DASHBOARD
    $routes->get('technician', [TechnicianDashboard::class, 'index'], ['filter' => 'group:technician']);

    // STUDENT/PIC ISSUE REPORTING
    $routes->get('report-issue', [IssueReportController::class, 'create'], ['filter' => 'group:student,staff,pic']);
    $routes->post('report-issue/store', [IssueReportController::class, 'store'], ['filter' => 'group:student,staff,pic']);

    // APPROVAL UI PAGE (accessible to PIC/MANAGER/ADMIN)
    $routes->get('approvals', [ApprovalsController::class, 'index'], ['filter' => 'group:pic,manager,admin']);
    $routes->get('external-requests', [ExternalRequestsController::class, 'index'], ['filter' => 'group:pic,manager,admin']);
    $routes->get('external-requests/(:num)', [ExternalRequestsController::class, 'show/$1'], ['filter' => 'group:pic,manager,admin']);
    $routes->post('external-requests/update/(:num)', [ExternalRequestsController::class, 'updateStatus/$1'], ['filter' => 'group:pic,manager,admin']);
});


// ====================================================================
// BOOKING APPROVAL ROUTES (SIMPLE VERSION)
// ====================================================================

$routes->group('booking', ['filter' => 'session'], function ($routes) {
    // Combined filter - any of these groups can access
    $routes->post('approve/(:num)', [BookingApprovalController::class, 'approve/$1'], 
        ['filter' => 'group:pic,manager,admin']
    );
    
    $routes->post('reject/(:num)', [BookingApprovalController::class, 'reject/$1'], 
        ['filter' => 'group:pic,manager,admin']
    );
});


// ====================================================================
// ADMIN PANEL ROUTES (STRICTLY ADMIN ONLY)
// ====================================================================

$routes->group('admin', ['filter' => 'group:admin'], function ($routes) {

    // Settings
    $routes->get('settings', [SettingsController::class, 'index']);
    $routes->post('settings/update', [SettingsController::class, 'update']);
    $routes->post('settings/save-slots', [SettingsController::class, 'saveSlots']);
    $routes->post('settings/run-scheduled-tasks', [SettingsController::class, 'runScheduledTasks']);

    // Laboratories CRUD
    $routes->get('labs', [LaboratoryAdminController::class, 'index']);
    $routes->get('labs/create', [LaboratoryAdminController::class, 'create']);
    $routes->post('labs/store', [LaboratoryAdminController::class, 'store']);
    $routes->get('labs/edit/(:num)', [LaboratoryAdminController::class, 'edit/$1']);
    $routes->post('labs/update/(:num)', [LaboratoryAdminController::class, 'update/$1']);
    $routes->post('labs/delete/(:num)', [LaboratoryAdminController::class, 'delete/$1']);

    // Assets CRUD - REORDERED (most specific first)
    $routes->get('assets/create', [AssetController::class, 'create']);
    $routes->post('assets/store', [AssetController::class, 'store']);
    $routes->get('assets/edit/(:num)', [AssetController::class, 'edit/$1']);
    $routes->post('assets/update/(:num)', [AssetController::class, 'update/$1']);
    $routes->post('assets/delete/(:num)', [AssetController::class, 'delete/$1']);
    $routes->get('assets/qr-labels', [AssetController::class, 'qrLabels']);
    $routes->get('assets', [AssetController::class, 'index']); // This should be LAST

    // User Management
    $routes->get('users', [UserManagementController::class, 'index']);
    $routes->get('users/export', [UserManagementController::class, 'exportCsv']);
    $routes->get('users/create', [UserManagementController::class, 'create']);
    $routes->post('users/store', [UserManagementController::class, 'store']);
    $routes->get('users/edit/(:num)', [UserManagementController::class, 'edit/$1']);
    $routes->post('users/update/(:num)', [UserManagementController::class, 'update/$1']);
    $routes->post('users/send-recovery/(:num)', [UserManagementController::class, 'sendRecovery/$1']);
    $routes->post('users/delete/(:num)', [UserManagementController::class, 'delete/$1']);
});


// ====================================================================
// TECHNICIAN ROUTES
// ====================================================================

$routes->group('technician', ['filter' => 'group:technician'], function ($routes) {
    $routes->get('maintenance', [MaintenanceController::class, 'index']);
    $routes->get('maintenance/create', [MaintenanceController::class, 'create']);
    $routes->get('maintenance/create/(:num)', [MaintenanceController::class, 'create/$1']);
    $routes->post('maintenance/store', [MaintenanceController::class, 'store']);
    $routes->get('maintenance/edit/(:num)', [MaintenanceController::class, 'edit/$1']);
    $routes->post('maintenance/update/(:num)', [MaintenanceController::class, 'update/$1']);
});


// ====================================================================
// SHIELD AUTH ROUTES (MUST BE LAST)
// ====================================================================

$routes->get('login/magic-link', [PasswordRecoveryController::class, 'loginView'], ['as' => 'magic-link']);
$routes->post('login/magic-link', [PasswordRecoveryController::class, 'loginAction']);
$routes->get('login/verify-magic-link', [PasswordRecoveryController::class, 'verify'], ['as' => 'verify-magic-link']);

service('auth')->routes($routes, ['except' => ['magic-link']]);

// Logout
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);

