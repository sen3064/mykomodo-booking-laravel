<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BookingAPIController;
use App\Http\Controllers\Notification\NotificationController;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => ['auth:sanctum']], function () {
   // sendBooking
   Route::post('/booking',[BookingAPIController::class,'booking']); 
   Route::post('/booking/boat',[BookingAPIController::class,'cartBookingBoat']);
   Route::post('/booking/homestay',[BookingAPIController::class,'cartBookingHomestay']);
   Route::post('/booking/tour',[BookingAPIController::class,'cartBookingTour']);
   Route::post('/booking/food-beverage',[BookingAPIController::class,'cartBookingFB']);
   Route::post('/booking/umkm',[BookingAPIController::class,'cartBookingUMKM']);
   Route::post('/booking/transportasi',[BookingAPIController::class,'cartBookingTransportasi']);
   Route::post('/booking/tiket-wisata',[BookingAPIController::class,'cartBookingTW']);

   // update booking data
   Route::put('/booking/boat/update/{code}',[BookingAPIController::class,'updateBookingStatus']);
   Route::put('/booking/homestay/update/{code}',[BookingAPIController::class,'updateBookingStatus']);
   Route::put('/booking/tour/update/{code}',[BookingAPIController::class,'updateBookingStatus']);
   Route::put('/booking/food-beverage/update/{code}',[BookingAPIController::class,'updateBookingStatus']);
   Route::put('/booking/umkm/update/{code}',[BookingAPIController::class,'updateBookingStatus']);
   Route::put('/booking/transportasi/update/{code}',[BookingAPIController::class,'updateBookingStatus']);
   Route::put('/booking/tiket-wisata/update/{code}',[BookingAPIController::class,'updateBookingStatus']);

   // user booking transactions
   Route::get('/my-transactions',[BookingAPIController::class,'getTransactions']);
   Route::get('/transactions',[BookingAPIController::class,'getTransactions']);
   Route::get('/my-transactions/{code}',[BookingAPIController::class,'transactionDetail']);
   Route::put('/my-transactions/update/{code}',[BookingAPIController::class,'updateTransactionStatus']);
   Route::get('/transaction-history',[BookingAPIController::class,'getHistoryTransactions']);
   Route::get('/transaction-history/{code}',[BookingAPIController::class,'historyTransactionDetail']);
   Route::get('/code-scanner/{code}',[BookingAPIController::class,'getBookingByCode']);

   // manual send notification
   Route::post('/send-notif',[NotificationController::class,'sendNotifAPI']);
});

Route::get('/test-mail/{code}',[BookingAPIController::class,'testSendMail']);
// Route::get('/transactions-test',[BookingAPIController::class,'getTransactions']);
