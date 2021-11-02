<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RouteResponseController;
use App\Http\Controllers\PaymentResponseController;
use App\Http\Controllers\CacheResponseController;
use App\Http\Controllers\AdminAppResponseController;
use App\Http\Controllers\AdminResponseController;
use App\Http\Controllers\ReportResponseController;
use App\Http\Controllers\PartnerResponseController;
use App\Http\Controllers\CronResponseController;
use App\Http\Controllers\OnetimeResponseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/v1/auth/sms/send/', [AuthController::class, 'SMSSend']);//ok
Route::post('/v1/auth/sms/verify/', [AuthController::class, 'SMSVerify']);//ok
Route::post('/v1/auth/logout/', [AuthController::class, 'logout']);//ok
Route::post('/admin/v1/auth/sms/send/', [AuthController::class, 'adminSMSSend']);//ok
Route::post('/admin/v1/auth/sms/verify/', [AuthController::class, 'adminSMSVerify']);//ok


Route::get('/{version}/data/initial/{table}/{page}/', [RouteResponseController::class, 'dataInitial']);//ok
Route::get('/{version}/data/check/{dbVersion}/', [RouteResponseController::class, 'dataCheck']);//ok
Route::get('/v1/home/', [RouteResponseController::class, 'home']);//ok
Route::get('/v2/home/', [RouteResponseController::class, 'home_v2']);//ok//Main API Problem//CDN_URL/S3_KEY/S3_SECRET
Route::get('/v1/medicines/{search}/{page}/', [RouteResponseController::class, 'medicines']);//checked //Argument 1 passed error
Route::get('/v1/sameGeneric/{g_id}/{page}/', [RouteResponseController::class, 'sameGeneric']);//OK// New Table field changed
Route::get('/{version}/medicine/{m_id}/', [RouteResponseController::class, 'medicineSingle']);//checked//getMedicine error
Route::get('/{version}/medicine/extra/{m_id}/', [RouteResponseController::class, 'medicineSingleExtra']);//ok


Route::get('/v1/medicine/price/{m_id}/', [RouteResponseController::class, 'medicinePrice']); //ok
Route::post('/v1/medicine/suggest/', [RouteResponseController::class, 'medicineSuggest']); //ok
Route::post('/v1/token/', [RouteResponseController::class, 'token']);//ok
Route::post('/v1/cart/details/', [RouteResponseController::class, 'cartDetails']);//check //done
Route::post('/v1/discount/check/', [RouteResponseController::class, 'dicountCheck']);//check //updated but need testing

Route::get('/v1/checkout/initiated/', [RouteResponseController::class, 'checkoutInitiated']);//ok

Route::post('/v1/order/add/', [RouteResponseController::class, 'orderAdd']);//
Route::get('/v1/order/{o_id}/', [RouteResponseController::class, 'orderSingle']);//OK
Route::get('/v1/invoice/{o_id}/{token}/', [RouteResponseController::class, 'invoice']);//Auth Check //Need to check Log
Route::get('/v1/invoice/bag/{o_id}/{token}/', [RouteResponseController::class, 'invoiceBag']);//Auth Check
Route::get('/v1/orders/{status}/{page}/', [RouteResponseController::class, 'orders']);//Check


Route::get('/v1/cashBalance/', [RouteResponseController::class, 'cashBalance']);//ok but check
Route::get('/v1/location/', [RouteResponseController::class, 'location']);//check new Client() done
Route::get('/v1/profile/', [RouteResponseController::class, 'profile']);//ok
Route::post('/v1/profile/', [RouteResponseController::class, 'profileUpdate']);//check file upload check need AWS credentials
Route::get('/v1/profile/prescriptions/', [RouteResponseController::class, 'prescriptions']);//check Meta:: DONE need AWS credentials
Route::get('/v1/offers/', [RouteResponseController::class, 'offers']);//ok
Route::get('/v1/faqsHeaders/', [RouteResponseController::class, 'FAQsHeaders']);//ok
Route::get('/v1/faqs/{slug}/', [RouteResponseController::class, 'FAQsReturn']);//ok
Route::get('/v1/locationData/', [RouteResponseController::class, 'locationData']);//ok




Route::prefix('/payment/v1')->group(function () {
        Route::get('/{o_id}/{o_token}/{method}/', [PaymentResponseController::class, 'home']);//check
        Route::get('/callback/{method}/', [PaymentResponseController::class, 'callback']);//check //create Nagad::instance()
        
        //$rc->addRoute( ['GET', 'POST'], '/success/{o_id:\d+}/{method}/', [ '\OA\PaymentResponse', 'success' ] );//How to 2 method create?
        // Route::get('/error/{o_id:\d+}/{method}/', [PaymentResponseController::class, 'error']);
        // Route::get('/ipn/{method}/', [PaymentResponseController::class, 'ipn']);
       
        // Route::get('/bKash/create/{o_id}/{secret}/', [PaymentResponseController::class, 'create']);
        // Route::get('/bKash/execute/{o_id}/{secret}/', [PaymentResponseController::class, 'execute']);
        // Route::get('/bKash/manualSuccess/{o_id}/', [PaymentResponseController::class, 'manualSuccess']);
        // Route::get('/bKash/refund/{o_id}/', [PaymentResponseController::class, 'refund']);
        // Route::get('/bKash/refundStatus/{o_id}/', [PaymentResponseController::class, 'refundStatus']);
});

Route::prefix('/cache/v1')->group(function () {
        Route::get('/flush/', [CacheResponseController::class, 'cacheFlush']);//ok but check
        Route::get('/stats/', [CacheResponseController::class, 'cacheStats']);//check Cache::stats()

        Route::get('/set/{key}/{value}/{group}/', [CacheResponseController::class, 'set']);//ok
        Route::get('/get/{key}/{group}/', [CacheResponseController::class, 'get']);//ok
        Route::get('/delete/{key}/{group}/', [CacheResponseController::class, 'delete']);//ok
    
});


Route::prefix('/adminApp/v1')->group(function () {
       Route::get('/orders/', [AdminAppResponseController::class, 'orders']);// Done Please check again
       Route::get('/later/', [AdminAppResponseController::class, 'later']);//need to check
       Route::get('/collections/', [AdminAppResponseController::class, 'collections']);// I think okay but check
       Route::get('/pendingCollection/', [AdminAppResponseController::class, 'pendingCollection']);//ok not sure
       Route::get('/zones/', [AdminAppResponseController::class, 'zones']);//simple// Need $this->user->u_role

       Route::post('/sendCollection/', [AdminAppResponseController::class, 'sendCollection']);// I think ok// But Check
       Route::post('/receivedCollection/{co_id}/', [AdminAppResponseController::class, 'receivedCollection']);//check
       Route::post('/statusTo/{o_id}/{status}/', [AdminAppResponseController::class, 'statusTo']);//I think need to check 
       Route::post('/internalStatusTo/{o_id}/{status}/', [AdminAppResponseController::class, 'internalStatusTo']);// Need to test
       Route::post('/issueStatusTo/{o_id}/{status}/', [AdminAppResponseController::class, 'issueStatusTo']);// Need to test
       Route::post('/saveInternalNote/{o_id}/', [AdminAppResponseController::class, 'saveInternalNote']);// Need to test
       Route::post('/sendDeSMS/{o_id:\d+}/', [AdminAppResponseController::class, 'sendDeSMS']); // Need to test
});

Route::prefix('/admin/v1')->group(function () {
        Route::get('/test/', [AdminResponseController::class, 'MyResponse']);// ok
        Route::get('/allLocations/', [AdminResponseController::class, 'allLocations']);//ok
        Route::get('/report/', [ReportResponseController::class, 'report']);// Query Check

        Route::prefix('/medicines')->group(function () {
                Route::get('/', [AdminResponseController::class, 'medicines']);//check its Search Related
                Route::post('/', [AdminResponseController::class, 'medicineCreate']);//ok need to check
                Route::get('/{m_id}/', [AdminResponseController::class, 'medicineSingle']);//ok
                Route::post('/{m_id}/', [AdminResponseController::class, 'medicineUpdate']);//I think ok //need test
                Route::get('/delete/{m_id}/', [AdminResponseController::class, 'medicineDelete']);//ok 
                Route::get('/delete/image/{m_id}/', [AdminResponseController::class, 'medicineImageDelete']);//need to check
         });

         Route::prefix('/users')->group(function () {
                Route::get('/', [AdminResponseController::class, 'users']);//ok but check
                Route::post('/', [AdminResponseController::class, 'userCreate']);//I think ok but check
                Route::get('/{u_id}/', [AdminResponseController::class, 'userSingle']);//ok
                Route::post('/{u_id}/', [AdminResponseController::class, 'userUpdate']);//ok
                Route::get('/delete/{u_id}/', [AdminResponseController::class, 'userDelete']);//ok
         });

         Route::prefix('/orders')->group(function () {
                Route::get('/', [AdminResponseController::class, 'orders']);//ok but check
                Route::post('/', [AdminResponseController::class, 'orderCreate']);//need test
                Route::get('/{o_id}/', [AdminResponseController::class, 'orderSingle']);// need to check
                Route::post('/{o_id}/', [AdminResponseController::class, 'orderUpdate']);// need test
                Route::get('/delete/{o_id}/', [AdminResponseController::class, 'orderDelete']);// I think ok
                Route::get('/{type}/{o_id}/', [AdminResponseController::class, 'adminGetType']);//ok I think
                Route::post('/{action}/{o_id}/', [AdminResponseController::class, 'adminPostAction']);//ok but check
                Route::post('/updateMany/', [AdminResponseController::class, 'orderUpdateMany']);// need delete() in cache
         });

         Route::prefix('/offlineOrders')->group(function () {
                Route::get('/', [AdminResponseController::class, 'offlineOrders']);// CacheUpdate check
                Route::post('/', [AdminResponseController::class, 'offlineOrderCreate']);// I think ok but check
                Route::get('/{o_id}/', [AdminResponseController::class, 'orderSingle']);// need to check
                Route::post('/{o_id}/', [AdminResponseController::class, 'offlineOrderUpdate']);// I think ok but check
                Route::get('/delete/{o_id}/', [AdminResponseController::class, 'orderDelete']);// I think ok but check
         });

       Route::prefix('/orderMedicines')->group(function () {
              Route::get('/', [AdminResponseController::class, 'orderMedicines']);// need test
              Route::get('/{om_id}/', [AdminResponseController::class, 'orderMedicineSingle']);// need test
              Route::post('/{om_id}/', [AdminResponseController::class, 'orderMedicineUpdate']);// need test
              Route::get('/delete/{om_id}/', [AdminResponseController::class, 'orderMedicineDelete']);//need test
       });
       Route::prefix('/laterMedicines')->group(function () {
              Route::get('/', [AdminResponseController::class, 'laterMedicines']);// need test
              Route::post('/savePurchaseRequest/', [AdminResponseController::class, 'savePurchaseRequest']);// need test
       });
       Route::prefix('/inventory')->group(function () {
              Route::get('/', [AdminResponseController::class, 'inventory']);// need test
              Route::get('/{i_id}/', [AdminResponseController::class, 'inventorySingle']);// need test
              Route::post('/{i_id}/', [AdminResponseController::class, 'inventoryUpdate']);// need test
              Route::get('/delete/{i_id}/', [AdminResponseController::class, 'inventoryDelete']);// need test
              Route::get('/balance/', [AdminResponseController::class, 'inventoryBalance']);// need test
       });
       Route::prefix('/purchases')->group(function () {
              Route::get('/', [AdminResponseController::class, 'purchases']);// need test
              Route::post('/', [AdminResponseController::class, 'purchaseCreate']);// need test
              Route::get('/{pu_id}/', [AdminResponseController::class, 'purchaseSingle']);// need test
              Route::post('/{pu_id}/', [AdminResponseController::class, 'purchaseUpdate']);// need test
              Route::get('/delete/{pu_id}/', [AdminResponseController::class, 'purchaseDelete']);// need test
              Route::get('/pendingTotal/', [AdminResponseController::class, 'purchasesPendingTotal']);// need test
              Route::post('/sync/', [AdminResponseController::class, 'purchasesSync']);// need test
       });
       Route::prefix('/collections')->group(function () {
              Route::get('/', [AdminResponseController::class, 'collections']);// need test
              Route::get('/{co_id}/', [AdminResponseController::class, 'collectionSingle']);// need test
       });
       Route::prefix('/ledger')->group(function () {
              Route::get('/', [AdminResponseController::class, 'ledger']);// need test
              Route::post('/', [AdminResponseController::class, 'ledgerCreate']);// need test
              Route::get('/{l_id}/', [AdminResponseController::class, 'ledgerSingle']);// need test
              Route::post('/{l_id}/', [AdminResponseController::class, 'ledgerUpdate']);// need test
              Route::get('/delete/{l_id}/', [AdminResponseController::class, 'ledgerDelete']);// need test
              Route::get('/balance/', [AdminResponseController::class, 'ledgerBalance']);// need test
       });

       Route::prefix('/companies')->group(function () {
              Route::get('/', [AdminResponseController::class, 'companies']);// need test
              Route::get('/{c_id}/', [AdminResponseController::class, 'companySingle']);// need test
       });
       Route::prefix('/generics')->group(function () {
              Route::get('/', [AdminResponseController::class, 'generics']);// need test
              Route::get('/{g_id}/', [AdminResponseController::class, 'genericSingle']);// need test
       });

       Route::prefix('/locations')->group(function () {
              Route::get('/', [AdminResponseController::class, 'locations']);// need test
              Route::get('/{l_id}/', [AdminResponseController::class, 'locationSingle']);// need test
       });

       Route::prefix('/locations')->group(function () {
              Route::get('/', [AdminResponseController::class, 'locations']);// need test
              Route::get('/{l_id}/', [AdminResponseController::class, 'locationSingle']);// need test
       });
       Route::prefix('/bags')->group(function () {
              Route::get('/', [AdminResponseController::class, 'bags']);// need test
              Route::post('/', [AdminResponseController::class, 'bagCreate']);// need test
              Route::get('/{b_id}/', [AdminResponseController::class, 'bagSingle']);// need test
              Route::post('/{b_id}/', [AdminResponseController::class, 'bagUpdate']);// need test
              Route::get('/delete/{b_id}/', [AdminResponseController::class, 'bagDelete']);// need test
       });
 });


 Route::prefix('/partner/v1')->group(function () {
       Route::get('/locationData/', [PartnerResponseController::class, 'locationData']);// need test

       Route::prefix('/orders')->group(function () {
              Route::get('/', [PartnerResponseController::class, 'orders']);// need test
              Route::post('/', [PartnerResponseController::class, 'orderCreate']);// need test
              Route::get('/{o_id}/', [PartnerResponseController::class, 'orderSingle']);// need test
       });
       Route::prefix('/users')->group(function () {
               Route::get('/{u_mobile}/', [PartnerResponseController::class, 'userSingle']);// need test
       });
});

Route::prefix('/cron/v1')->group(function () {
       Route::get('/daily/{type}/', [CronResponseController::class, 'daily']);// need test
       Route::get('/hourly/{type}/', [CronResponseController::class, 'hourly']);// need test
       Route::get('/halfhourly/{type}/', [CronResponseController::class, 'halfhourly']);// need test
});

Route::prefix('/onetime/v1')->group(function () {
       Route::get('/updateLocationsTable/', [OnetimeResponseController::class, 'updateLocationsTable']);// need test
       Route::get('/sitemap/', [OnetimeResponseController::class, 'sitemap']);// need test

       Route::get('/stockUpdate/{rob}/{by}/{id}/', [OnetimeResponseController::class, 'stockUpdate']);// need test
       Route::get('/deliverymanUpdate/{prev}/{curr}/', [OnetimeResponseController::class, 'deliverymanUpdate']);// need test
       Route::get('/priceUpdate/{discountPercent}/{by}/{id}/{prevDiscountPercent}/', [OnetimeResponseController::class, 'priceUpdate']);// need test

       Route::get('/medicineCSVImport/{number:\d+}/', [OnetimeResponseController::class, 'medicineCSVImport']);// need test

       Route::get('/search/medicine/indices/delete/', [Medicine::class, 'indicesDelete']);// need test
       Route::get('/search/medicine/indices/create/', [Medicine::class, 'indicesCreate']);// need test
       Route::get('/search/medicine/bulkIndex/', [Medicine::class, 'bulkIndex']);// need test
});
