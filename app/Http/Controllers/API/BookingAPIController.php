<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use App\Models\Boat;
use App\Models\PaymentChannel;
use Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderBoatMail;
use App\Mail\OrderBookMail;
use App\Mail\OrderTourMail;
use App\Mail\OrderMail;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\User;
use App\Http\Controllers\Notification\NotificationController;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\OrderItem;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomBooking;
use App\Models\Location;
use App\Models\MediaFile;
use App\Models\Tour;
use App\Models\TourParent;
use App\Models\BravoReview;
use App\Models\BravoCar;
use App\Models\TiketWisata;

class BookingAPIController extends Controller
{
    protected $prod = false;
    public $token;
    public $lws = 'https://nr.bismut.id';
    public $wurl = 'https://m.pulo1000.com';
    public $murl = 'https://mitra.pulo1000.com';
    public $notif = [
        'target' => '',
        'target_role' => 'wisatawan',
        'target_name' => '',
        'notif_type' => [],
        'channel' => '',
        'type' => '',
        'toAdmin' => 0,
        'link' => '',
        'headings' => '',
        'message' => '',
        'player_ids' => [],
        'order' => '',
        'mail_target_type' => '',
    ];
    public $notif_mitra = [
        'target' => '',
        'target_role' => 'mitra',
        'target_name' => '',
        'notif_type' => [],
        'channel' => '',
        'type' => '',
        'toAdmin' => 0,
        'link' => '',
        'headings' => '',
        'message' => '',
        'player_ids' => [],
        'order' => '',
        'mail_target_type' => '',
    ];
    public $app_id = 2;
    //
    public function booking(Request $request)
    {
        // return response()->json(['request'=>$request->all()]);
        if (strtolower($request->object_model) == 'boat') {
            $this->cartBookingBoat($request);
        } elseif (strtolower($request->object_model) == 'hotel') {
            $this->cartBookingHomestay($request);
        }
    }

    public function cartBookingBoat(Request $request)
    {
        $customer = Auth::user();
        if ($this->ownBoat($customer->id)) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Anda tidak dapat memesan tiket kapal anda sendiri',
                ]
            );
        }
        $jml_penumpang = $request->penumpang;
        $penumpang = 0;
        $penumpang_anak = 0;
        $data_penumpang = json_decode(json_encode($request->data_penumpang));
        $boat = json_decode(json_encode($request->boat));
        $pemesan = json_decode(json_encode($request->pemesan));
        // $datas=$request->boat;
        $datas = json_decode(json_encode($request->boat));
        $datas->data_penumpang = ['dewasa' => [], 'anak' => []];
        for ($i = 0; $i < $jml_penumpang; $i++) {
            $temp = [
                "no_identitas" => $data_penumpang[$i]->identitas,
                "nama" => $data_penumpang[$i]->nama,
                "umur" => $data_penumpang[$i]->umur,
                "jenis_kelamin" => $data_penumpang[$i]->jenis_kelamin
            ];
            if ((int)$data_penumpang[$i]->umur < 17) {
                $penumpang_anak++;
                array_push($datas->data_penumpang['anak'], $temp);
            } else {
                $penumpang++;
                array_push($datas->data_penumpang['dewasa'], $temp);
            }
        }
        $datas->penumpang = $penumpang;
        $datas->penumpang_anak = $penumpang_anak;
        $datas->admin_fee = $request->admin_fee ?? getAdminFee((float)$datas->price) * $datas->penumpang;
        $datas->admin_fee_child = $request->child_fee ?? getAdminFee((float)$datas->price) * $datas->penumpang_anak;
        $list_buyer_fees = json_encode(['admin_fee' => ['dewasa' => $datas->admin_fee, 'anak' => $datas->admin_fee_child], 'transfer_fee' => 4440]);
        // return response(json_encode($datas));
        $booking = new Booking();
        $booking->code = $this->generateBoatCode();
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $datas->id;
        $booking->object_model = 'boat';
        // $booking->vendor_id = $datas->create_user;
        $booking->vendor_id = $datas->agent_id;
        $booking->customer_id = $customer->id;
        $booking->data_detail = json_encode($datas);
        $booking->total = ($datas->penumpang * $datas->price) + $datas->admin_fee + ($datas->penumpang_anak * $datas->price) + $datas->admin_fee_child;
        $booking->total_guests = $jml_penumpang;
        // $booking->start_date = $datas->booking_date.' '.$datas->departure_time;
        // $booking->end_date = $datas->booking_date.' '.$datas->arrival_time;
        $booking->start_date = $datas->booking_date . ' ' . $datas->departure_time;
        $booking->end_date = $datas->booking_date . ' ' . ($datas->arrival_time ?? '16:00');
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        // $booking->total_before_fees = ($datas->penumpang*$datas->price)-(5000*$datas->penumpang);
        $booking->total_before_fees = ($datas->penumpang * $datas->price) + ($datas->penumpang_anak * $datas->price);
        $booking->first_name = $pemesan->firstname;
        $booking->last_name = $pemesan->lastname;
        $booking->email = $pemesan->email;
        $booking->address = $pemesan->line1 ?? NULL;
        $booking->country = $pemesan->country ?? NULL;
        $booking->zip_code = $pemesan->zipcode ?? NULL;
        $booking->city = $pemesan->city ?? NULL;
        $booking->state = $pemesan->province ?? NULL;
        $booking->phone = $pemesan->phone;
        $booking->gateway = 'xendit';
        $booking->save();
        // dd($facilities);
        $datas->total_before_fees = $booking->total_before_fees;
        $datas->total_with_fees = $booking->total;
        $datas->berangkat = $datas->booking_date;
        $datas->pulang = $datas->back_date ?? NULL;
        $booking->data_detail = json_encode($datas);
        $booking->save();

        return response()->json(
            [
                'success' => true,
                'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
                'boat_data' => $datas,
                'booking_data' => $booking
            ]
        );
    }

    public function generateBoatCode()
    {
        $code = 'B-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Booking::where('code', $code)->doesntExist())
            return $code;
        $this->generateBoatCode();
    }

    public function ownBoat($uid)
    {
        $boatCount = Boat::where('agent_id', $uid)->orWhere('create_user', $uid)->count();
        if ($boatCount > 0) {
            return true;
        }
        return false;
    }

    public function cartBookingHomestay(Request $request)
    {
        $customer = Auth::user();
        if ($this->ownHomestay($request->hotel_id)) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Anda tidak dapat memesan homestay Anda sendiri',
                    'user' => $customer
                ]
            );
        }

        $hotel = Hotel::find($request->hotel_id);

        // $image_url = MediaFile::find($hotel->image_id)->file_path;

        $facilities_detail = null;

        $data_detail = HotelRoom::find($request->room_id)->toArray();
        $getimg = MediaFile::find($data_detail['image_id']);
        $pre_url = $this->prod ? 'https://cdn.mykomodo.com/uploads/' : 'https://cdn.mykomodo.kabtour.com/uploads/';
        $data_detail['banner'] = [
            "original" => $pre_url . $getimg->file_path,
            "200x150" => $getimg->file_resize_200 != null ? $pre_url . $getimg->file_resize_200 : null,
            "250x200" => $getimg->file_resize_250 != null ? $pre_url . $getimg->file_resize_250 : null,
            "400x350" => $getimg->file_resize_400 != null ? $pre_url . $getimg->file_resize_400 : null,
        ];
        $data_detail['file_path'] = $getimg->file_path;
        $data_detail['file_name'] = $getimg->file_name;
        $data_detail['image_url'] = $getimg->file_path;
        $data_detail['hotel_id'] = $hotel->id;
        $data_detail['hotel_title'] = $hotel->title;
        $data_detail['locname'] = Location::find($hotel->location_id)->name;
        $data_detail['location_id'] = $hotel->location_id;
        $data_detail['penginap'] = isset($data_detail['max_guests']) ? $data_detail['max_guests'] : 1;
        $data_detail['datestart'] = date('Y-m-d', strtotime($request->checkin));
        $data_detail['dateend'] = date('Y-m-d', strtotime($request->checkout));
        $data_detail['total_room'] = $request->total_room;
        $data_detail['booking_price'] = $request->price;
        $list_gallery = [];
        $listgal = explode(',', $data_detail['gallery']);
        foreach ($listgal as $k => $v) {
            if ($v) {
                $getgal = MediaFile::find($v)->file_path;
                $list_gallery[] = $getgal;
            }
        }
        $data_detail['list_gallery'] = $list_gallery;
        $origin = date_create(date('Y-m-d', strtotime($request->checkin)));
        $target = date_create(date('Y-m-d', strtotime($request->checkout)));
        $interval = number_format((strtotime($request->checkout) - strtotime($request->checkin)) / (24 * 3600), 0, ',', '.');
        $admin_fee = $request->admin_fee ?? getAdminFee($request->price) * $interval * $request->total_room;
        $list_buyer_fees = json_encode(['admin_fee' => $admin_fee, 'transfer_fee' => 4440]);

        // $customer = Auth::user();
        $booking = new Booking();
        $booking->code = $this->generateHotelCode();
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $hotel->id;
        $booking->object_model = 'hotel';
        $booking->vendor_id = $hotel->create_user;
        $booking->customer_id = $customer->id;
        $booking->total = ($request->price * $interval * $request->total_room) + $admin_fee;
        $booking->data_detail = json_encode($data_detail);
        $booking->facilities_detail = json_encode($facilities_detail) ?? null;
        $booking->total_guests = $data_detail['max_guests'] ?? 1;
        $booking->start_date = $data_detail['datestart'] . ' ' . $hotel->check_in_time;
        $booking->end_date = $data_detail['dateend'] . ' ' . $hotel->check_out_time;
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        $booking->total_before_fees = ($request->price * $request->total_room * $interval);
        $booking->first_name = $request->first_name ?? $customer->first_name;
        $booking->last_name = $request->last_name ?? $customer->last_name;
        $booking->email = $request->email ?? $customer->email;
        $booking->address = $request->line1 ?? $request->address ?? $customer->address;
        // $booking->address2 = $request->line2 ?? $request->address2 ?? $customer->address;
        $booking->country = $request->country ?? $customer->country;
        $booking->zip_code = $request->zipcode ?? $customer->zip_code;
        $booking->city = $request->city ?? $customer->city;
        $booking->state = $request->province ?? $customer->state;
        $booking->phone = $request->phone ?? $customer->phone;
        $booking->gateway = 'xendit';
        $booking->status = 'waiting';
        $booking->save();

        // $this->sendBookingConfirmationMail($booking);


        $bookingRoom = new HotelRoomBooking();
        $bookingRoom->room_id = $data_detail['id'];
        $bookingRoom->parent_id = $hotel->id;
        $bookingRoom->booking_id = $booking->id;
        $bookingRoom->start_date = $data_detail['datestart'] . ' ' . $hotel->check_in_time;
        $bookingRoom->end_date = $data_detail['dateend'] . ' ' . $hotel->check_out_time;
        $bookingRoom->number = $request->total_room;
        $bookingRoom->price = $request->price * $interval;
        $bookingRoom->create_user = $customer->id;
        $bookingRoom->save();

        // return response()->json(
        //     [
        //         'success' => true,
        //         'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
        //         'object_data' => ['homestay' => $hotel, 'room' => $data_detail],
        //         'booking_data' => $booking
        //     ]
        // );
        return $this->updateBookingStatus($request, $booking->code);
    }

    public function generateHotelCode()
    {
        $code = 'H-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Booking::where('code', $code)->doesntExist())
            return $code;
        $this->generateHotelCode();
    }

    public function ownHomestay($hotel_id)
    {
        $hotel = Hotel::find($hotel_id);
        if ($hotel) {
            if ($hotel->create_user == Auth::id()) {
                return true;
            }
        }
        return false;
    }

    public function cartBookingTour(Request $request)
    {
        // // dd($request->all());
        // $this->token = $request->bearerToken();
        // // dd($this->token);
        // $bookurl = "https://tourapidev.pulo1000.com/v2/cartBookingTour";
        // $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
        // $response = $post->post($bookurl, $request->all());
        // $result = json_decode(json_encode($response->json()));
        // dd($result);
        $tour = Tour::find($request->tour_id);
        $customer = Auth::user();
        if ($tour->create_user == $customer->id) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Anda tidak dapat memesan Paket Anda sendiri',
                    'user' => $customer
                ]
            );
        }
        $media = MediaFile::find($tour->image_id);
        $pre_url = $this->prod ?  'https://cdn.mykomodo.com/uploads/' : 'https://cdn.mykomodo.kabtour.com/uploads/';
        $tour->banner = [
            "original" => $pre_url . $media->file_path,
            "200x150" => $media->file_resize_200 != null ? $pre_url . $media->file_resize_200 : null,
            "250x200" => $media->file_resize_250 != null ? $pre_url . $media->file_resize_250 : null,
            "400x350" => $media->file_resize_400 != null ? $pre_url . $media->file_resize_400 : null,
        ];
        // $tour_parent = TourParent::find($request->parent_id);

        $price = $request->price;
        $total = $request->total_price;
        $total_guests = $request->total_guests;
        $is_private = $request->is_private;

        $start_date = new \DateTime($request->start_date);

        $admin_fee = $request->admin_fee ?? getAdminFee($price);
        if (!$is_private) {
            $admin_fee = $admin_fee * $total_guests;
        }

        $list_buyer_fees = json_encode(['admin_fee' => $admin_fee, 'transfer_fee' => 4440]);
        $start_date = new \DateTime($request->start_date);

        $data_detail = [];
        $data_detail['tour'] = $tour;
        // $data_detail['tour_parent'] = $tour_parent;
        $data_detail['datestart'] = $start_date->format('Y-m-d H:i:s');
        $data_detail['total_guests'] = $request->total_guests;
        $start_date->modify('+ ' . max(1, $tour->duration) . ' hours');
        $data_detail['dateend'] = $start_date->format('Y-m-d H:i:s');
        // $data_detail['locname'] = Location::find($tour_parent->location_id)->name;
        $data_detail['locname'] = Location::find($tour->location_id)->name;

        $booking = new Booking();
        $booking->code = $this->generateTourCode();
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $tour->id;
        $booking->object_model = 'tour';
        $booking->vendor_id = $tour->create_user;
        $booking->customer_id = $customer->id;
        $booking->total = $total + $admin_fee;
        $booking->data_detail = json_encode($data_detail);
        $booking->facilities_detail = NULL;
        $booking->total_guests = $total_guests ?? 1;
        $booking->start_date = $data_detail['datestart'];
        $booking->end_date = $data_detail['dateend'];
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        $booking->total_before_fees = $total;
        $booking->first_name = $request->first_name ?? $customer->first_name;
        $booking->last_name = $request->last_name ?? $customer->last_name;
        $booking->email = $request->email ?? $customer->email;
        $booking->address = $request->line1 ?? $request->address ?? $customer->address;
        // $booking->address2 = $request->line2 ?? $request->address2 ?? $customer->address;
        $booking->country = $request->country ?? $customer->country;
        $booking->zip_code = $request->zipcode ?? $customer->zip_code;
        $booking->city = $request->city ?? $customer->city;
        $booking->state = $request->province ?? $customer->state;
        $booking->phone = $request->phone ?? $customer->phone;
        $booking->gateway = 'xendit';
        $booking->status = 'waiting';
        $booking->save();

        // return response()->json(
        //     [
        //         'success' => true,
        //         'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
        //         'object_data' => $data_detail,
        //         'booking_data' => $booking
        //     ]
        // );
        return $this->updateBookingStatus($request, $booking->code);
    }

    public function generateTourCode()
    {
        $code = 'T-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Booking::where('code', $code)->doesntExist())
            return $code;
        $this->generateTourCode();
    }

    public function cartBookingFB(Request $request)
    {
        $vendor_id = 0;
        $total = 0;
        $subtotal = 0;
        $req_admin_fee = $request->admin_fee ?? 0;
        $admin_fee = $request->admin_fee ?? 0;
        $orderItems = [];
        $user = Auth::user();
        $order = new Order();
        $order->user_id = $user->id;
        $order->app_id = $request->app_id ?? $this->app_id;
        $order->subtotal = 0;
        $order->discount = 0;
        $order->tax = 0;
        $order->total = 0;
        $order->firstname = $request->firstname ?? $request->first_name ?? $user->first_name;
        $order->lastname = $request->lastname ?? $request->last_name ?? $user->last_name;
        $mobile = $request->phone ?? $user->phone;
        if (substr($mobile, 0, 1) == '0') {
            $temp_phone = substr($mobile, 1);
            $mobile = '62' . $temp_phone;
        }
        $order->mobile = $mobile;
        $order->email = $request->email ?? $user->email;
        $order->line1 = $request->line1 ?? $request->address ?? $user->address;
        $order->line2 = $request->line2 ?? $request->address2 ?? $user->address2;
        $order->city = $request->city ?? $user->city;
        $order->province = $request->province ?? $user->state;
        $order->country = $request->country ?? $user->country;
        $order->zipcode = $request->zipcode ?? $user->zip_code;
        $order->status = 'waiting';
        $order->mode_pay = $request->payment_channel ?? 'xendit';
        $order->shiptype = $request->shipping_type ?? 'Diantar';
        $order->code_booking = $this->generateFBCode('FB');
        $order->is_shipping_different = $request->shipToDiff ? 1 : 0;
        $order->save();

        foreach ($request->items as $item) {
            // dd($item);
            // $itemPrice = intval($item['price']) + intval($item['variants']['price'] ?? 0);
            $itemPrice = isset($item['variants']['id']) ? intval($item['variants']['price'] ?? 0) : intval($item['price'] ?? 0);
            $orderItem = new OrderItem();
            $orderItem->app_id = $request->app_id ?? $this->app_id;
            $isVariant = isset($item['product_id']) ? true : false;
            $product = Product::find($isVariant ? $item['product_id'] : $item['id']);
            // dd($orderItem);
            $orderItem->product_id = $isVariant ? $item['product_id'] : $item['id'];
            $orderItem->variant_id = $isVariant ? $item['id'] : $item['variants']['id'] ?? null;
            $orderItem->name_product = $isVariant ?  $product->name . '-' . $item['name'] : $item['name'];
            $orderItem->image_product = $item['banner']['250x200'] ?? 'https://cdn1.iconfinder.com/data/icons/fillio-food-kitchen-and-cooking/48/food_-_dish-1024.png';
            $orderItem->order_id = $order->id;
            $orderItem->mode_pay = $order->mode_pay;
            $orderItem->ship_address = $order->line1;
            $orderItem->shiptype = $order->shiptype;
            // $orderItem->sku = $item->sku;
            $orderItem->status = 'waiting';
            $orderItem->code_booking = $order->code_booking;
            $orderItem->price = $itemPrice * $item['quantity'];
            $orderItem->quantity = $item['quantity'];
            // dd($orderItem);
            $orderItem->save();
            array_push($orderItems, $orderItem);
            $subtotal += $orderItem->price;
            $total += $orderItem->price;

            // mengurangi stok produk
            // $product = Product::find($item->id);
            // $product = Product::find($item['id']) ?? Product::find($item['product_id']);
            $substock = $product->stock_quantity - $orderItem->quantity;
            $product->stock_quantity = $substock;
            if ($product->stock_quantity == 0) {
                $product->stock_status = 'kosong';
            }
            $product->save();
            $productVariant = $isVariant ? ProductVariant::find($item['id']) : (isset($item['variants']['id']) ? ProductVariant::find($item['variants']['id']) :  null);
            if ($productVariant) {
                $substock = $productVariant->stock_quantity - $orderItem->quantity;
                $productVariant->stock_quantity = $substock;
                if ($productVariant->stock_quantity == 0) {
                    $productVariant->stock_status = 'kosong';
                }
                $productVariant->save();
            }

            if ($req_admin_fee == 0) {
                $admin_fee = $admin_fee + (getAdminFee($itemPrice) * $item['quantity']);
            }
            $total += $admin_fee;
            $vendor_id = $product->create_user;
        }
        $order->subtotal = $subtotal;
        $order->total = $total + ($request->shipping_cost ?? 0);
        $order->save();

        $customer = $user;

        $list_buyer_fees = json_encode(['admin_fee' => $admin_fee, 'transfer_fee' => 4440, 'shipping_cost' => $request->shipping_cost ?? 0]);

        $booking = new Booking();
        $booking->code = $order->code_booking;
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $order->id;
        $booking->object_model = 'food-beverages';
        $booking->vendor_id = $vendor_id;
        $booking->customer_id = $customer->id;
        $booking->total = $order->total;
        $booking->data_detail = json_encode($request->all());
        $booking->facilities_detail = NULL;
        $booking->total_guests = $total_guests ?? 1;
        $booking->start_date = null;
        $booking->end_date = null;
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        $booking->total_before_fees = $order->subtotal;
        $booking->first_name = $request->first_name ?? $customer->first_name;
        $booking->last_name = $request->last_name ?? $customer->last_name;
        $booking->email = $request->email ?? $customer->email;
        $booking->address = $request->line1 ?? $request->address ?? $customer->address;
        $booking->address2 = $request->line2 ?? $request->address2 ?? $customer->address;
        $booking->country = $request->country ?? $customer->country;
        $booking->zip_code = $request->zipcode ?? $customer->zip_code;
        $booking->city = $request->city ?? $customer->city;
        $booking->state = $request->province ?? $customer->state;
        $booking->phone = $request->phone ?? $customer->phone;
        $booking->gateway = 'xendit';
        $booking->status = 'waiting';
        $booking->save();

        // return response()->json([
        //     'success'=>true,
        //     'message'=>'Order ditambahkan',
        //     'data'=>[
        //         'admin_fee'=>$admin_fee,
        //         'order'=>$order,
        //         'items'=>$orderItems
        //     ]
        // ]);

        // return response()->json(
        //     [
        //         'success' => true,
        //         'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
        //         'object_data' => $request->all(),
        //         'booking_data' => $booking
        //     ]
        // );
        return $this->updateBookingStatus($request, $booking->code);
    }

    private function generateFBCode($prefix = 'FB')
    {
        $code = $prefix . '-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Order::where('code_booking', $code)->doesntExist())
            return $code;
        $this->generateFBCode($prefix);
    }

    public function cartBookingUMKM(Request $request)
    {
        $vendor_id = 0;
        $total = 0;
        $subtotal = 0;
        $req_admin_fee = $request->admin_fee ?? 0;
        $admin_fee = $request->admin_fee ?? 0;
        $orderItems = [];
        $user = Auth::user();
        $order = new Order();
        $order->user_id = $user->id;
        $order->app_id = $request->app_id ?? $this->app_id;
        $order->subtotal = 0;
        $order->discount = 0;
        $order->tax = 0;
        $order->total = 0;
        $order->firstname = $request->firstname ?? $request->first_name ?? $user->first_name;
        $order->lastname = $request->lastname ?? $request->last_name ?? $user->last_name;
        $mobile = $request->phone ?? $user->phone;
        if (substr($mobile, 0, 1) == '0') {
            $temp_phone = substr($mobile, 1);
            $mobile = '62' . $temp_phone;
        }
        $order->mobile = $mobile;
        $order->email = $request->email ?? $user->email;
        $order->line1 = $request->line1 ?? $request->address ?? $user->address;
        $order->line2 = $request->line2 ?? $request->address2 ?? $user->address2;
        $order->city = $request->city ?? $user->city;
        $order->province = $request->province ?? $user->state;
        $order->country = $request->country ?? $user->country;
        $order->zipcode = $request->zipcode ?? $user->zip_code;
        $order->status = 'waiting';
        $order->mode_pay = $request->payment_channel ?? 'xendit';
        $order->shiptype = $request->shipping_type ?? 'Diambil';
        $order->code_booking = $this->generateUMKMCode('U');
        $order->is_shipping_different = $request->shipToDiff ? 1 : 0;
        $order->save();

        foreach ($request->items as $item) {
            // dd($item);
            // $itemPrice = intval($item['price']) + intval($item['variants']['price'] ?? 0);
            // if (is_array($item) && isset($item['id'])) {
            // dd($item);
            $itemPrice = isset($item['variants']['id']) ? intval($item['variants']['price'] ?? 0) : intval($item['price'] ?? 0);
            $orderItem = new OrderItem();
            $orderItem->app_id = $request->app_id ?? $this->app_id;
            $isVariant = isset($item['product_id']) ? true : false;
            // dd($isVariant);
            $product = Product::find($isVariant ? $item['product_id'] : $item['id']);
            // dd($orderItem);
            $orderItem->product_id = $isVariant ? $item['product_id'] : $item['id'];
            $orderItem->variant_id = $isVariant ? $item['id'] : $item['variants']['id'] ?? null;
            $orderItem->name_product = $isVariant ?  $product->name . '-' . $item['name'] : $item['name'];
            $orderItem->image_product = !isset($item['banner']) ? (!isset($item['image']) ? 'https://cdn4.iconfinder.com/data/icons/check-out-vol-1-colored/48/JD-22-1024.png' : $item['image']['250x200']) : $item['banner']['250x200'];
            $orderItem->order_id = $order->id;
            $orderItem->mode_pay = $order->mode_pay;
            $orderItem->ship_address = $order->line1;
            $orderItem->shiptype = $order->shiptype;
            // $orderItem->sku = $item->sku;
            $orderItem->status = 'waiting';
            $orderItem->code_booking = $order->code_booking;
            $orderItem->price = $itemPrice * $item['quantity'];
            $orderItem->quantity = $item['quantity'];
            // dd($orderItem);
            $orderItem->save();
            array_push($orderItems, $orderItem);
            $subtotal += $orderItem->price;
            $total += $orderItem->price;

            // mengurangi stok produk
            // $product = Product::find($item->id);
            // $product = Product::find($item['id']) ?? Product::find($item['product_id']);
            $substock = $product->stock_quantity - $orderItem->quantity;
            $product->stock_quantity = $substock;
            if ($product->stock_quantity == 0) {
                $product->stock_status = 'kosong';
            }
            $product->save();
            $productVariant = $isVariant ? ProductVariant::find($item['id']) : (isset($item['variants']['id']) ? ProductVariant::find($item['variants']['id']) :  null);
            if ($productVariant) {
                $substock = $productVariant->stock_quantity - $orderItem->quantity;
                $productVariant->stock_quantity = $substock;
                if ($productVariant->stock_quantity == 0) {
                    $productVariant->stock_status = 'kosong';
                }
                $productVariant->save();
            }

            if ($req_admin_fee == 0) {
                $admin_fee = $admin_fee + (getAdminFee($itemPrice) * $item['quantity']);
            }
            $total += $admin_fee;
            $vendor_id = $product->create_user;
            // }
        }
        $order->subtotal = $subtotal;
        $order->total = $total + ($request->shipping_cost ?? 0);
        // dd($order);
        $order->save();

        $customer = $user;

        $list_buyer_fees = json_encode(['admin_fee' => $admin_fee, 'transfer_fee' => 4440, 'shipping_cost' => $request->shipping_cost ?? 0]);

        $booking = new Booking();
        $booking->code = $order->code_booking;
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $order->id;
        $booking->object_model = 'umkm';
        $booking->vendor_id = $vendor_id;
        $booking->customer_id = $customer->id;
        $booking->total = $order->total;
        $booking->data_detail = json_encode($request->all());
        $booking->facilities_detail = NULL;
        $booking->total_guests = $total_guests ?? 1;
        $booking->start_date = null;
        $booking->end_date = null;
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        $booking->total_before_fees = $order->subtotal;
        $booking->first_name = $request->first_name ?? $customer->first_name;
        $booking->last_name = $request->last_name ?? $customer->last_name;
        $booking->email = $request->email ?? $customer->email;
        $booking->address = $request->line1 ?? $request->address ?? $customer->address;
        $booking->address2 = $request->line2 ?? $request->address2 ?? $customer->address;
        $booking->country = $request->country ?? $customer->country;
        $booking->zip_code = $request->zipcode ?? $customer->zip_code;
        $booking->city = $request->city ?? $customer->city;
        $booking->state = $request->province ?? $customer->state;
        $booking->phone = $request->phone ?? $customer->phone;
        $booking->gateway = 'xendit';
        // $booking->status = 'waiting';
        $booking->save();

        // return response()->json([
        //     'success'=>true,
        //     'message'=>'Order ditambahkan',
        //     'data'=>[
        //         'admin_fee'=>$admin_fee,
        //         'order'=>$order,
        //         'items'=>$orderItems
        //     ]
        // ]);

        // return response()->json(
        //     [
        //         'success' => true,
        //         'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
        //         'object_data' => $request->all(),
        //         'booking_data' => $booking
        //     ]
        // );
        return $this->updateBookingStatus($request, $booking->code);
    }

    private function generateUMKMCode($prefix = 'U')
    {
        $code = $prefix . '-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Order::where('code_booking', $code)->doesntExist())
            return $code;
        $this->generateUMKMCode($prefix);
    }

    public function cartBookingTransportasi(Request $request)
    {
        // // dd($request->all());
        // $this->token = $request->bearerToken();
        // // dd($this->token);
        // $bookurl = "https://tourapidev.pulo1000.com/v2/cartBookingTour";
        // $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
        // $response = $post->post($bookurl, $request->all());
        // $result = json_decode(json_encode($response->json()));
        // dd($result);
        $car = BravoCar::find($request->car_id);
        $customer = Auth::user();
        if ($car->create_user == $customer->id) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Anda tidak dapat memesan Paket Anda sendiri',
                    'user' => $customer
                ]
            );
        }
        $media = MediaFile::find($car->image_id);
        $pre_url = $this->prod ?  'https://cdn.mykomodo.kabtour.com/uploads/' : 'https://cdn.mykomodo.kabtour.com/uploads/';
        $car->banner = [
            "original" => $pre_url . $media->file_path,
            "200x150" => $media->file_resize_200 != null ? $pre_url . $media->file_resize_200 : null,
            "250x200" => $media->file_resize_250 != null ? $pre_url . $media->file_resize_250 : null,
            "400x350" => $media->file_resize_400 != null ? $pre_url . $media->file_resize_400 : null,
        ];
        // $tour_parent = TourParent::find($request->parent_id);

        $price = $request->price;
        $total = $request->total_price;
        $total_guests = $request->total_guests ?? 1;

        $start_date = new \DateTime($request->start_date);
        $end_date = new \DateTime($request->end_date);

        $admin_fee = $request->admin_fee ?? getAdminFee($total);
        // if (!$is_private) {
        //     $admin_fee = $admin_fee * $total_guests;
        // }

        $list_buyer_fees = json_encode(['admin_fee' => $admin_fee, 'transfer_fee' => 4440]);
        // $start_date = new \DateTime($request->start_date);

        $data_detail = [];
        $data_detail['transportasi'] = $car;
        // $data_detail['tour_parent'] = $tour_parent;
        $data_detail['datestart'] = $start_date->format('Y-m-d H:i:s');
        $data_detail['total_guests'] = $request->total_guests;
        // $start_date->modify('+ ' . max(1, $tour->duration) . ' hours');
        $data_detail['dateend'] = $end_date->format('Y-m-d H:i:s');
        // $data_detail['locname'] = Location::find($tour_parent->location_id)->name;
        $data_detail['locname'] = Location::find($car->location_id)->name;

        $booking = new Booking();
        $booking->code = $this->generateRentCode();
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $car->id;
        $booking->object_model = 'transportasi';
        $booking->vendor_id = $car->create_user;
        $booking->customer_id = $customer->id;
        $booking->total = $total + $admin_fee;
        $booking->data_detail = json_encode($data_detail);
        $booking->facilities_detail = NULL;
        $booking->total_guests = $total_guests ?? 1;
        $booking->start_date = $data_detail['datestart'];
        $booking->end_date = $data_detail['dateend'];
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        $booking->total_before_fees = $total;
        $booking->first_name = $request->first_name ?? $customer->first_name;
        $booking->last_name = $request->last_name ?? $customer->last_name;
        $booking->email = $request->email ?? $customer->email;
        $booking->address = $request->line1 ?? $request->address ?? $customer->address;
        $booking->address2 = $request->line2 ?? $request->address2 ?? $customer->address;
        $booking->country = $request->country ?? $customer->country;
        $booking->zip_code = $request->zipcode ?? $customer->zip_code;
        $booking->city = $request->city ?? $customer->city;
        $booking->state = $request->province ?? $customer->state;
        $booking->phone = $request->phone ?? $customer->phone;
        $booking->gateway = 'xendit';
        $booking->status = 'waiting';
        $booking->save();

        // return response()->json(
        //     [
        //         'success' => true,
        //         'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
        //         'object_data' => $data_detail,
        //         'booking_data' => $booking
        //     ]
        // );
        return $this->updateBookingStatus($request, $booking->code);
    }

    public function generateRentCode()
    {
        $code = 'R-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Booking::where('code', $code)->doesntExist())
            return $code;
        $this->generateRentCode();
    }

    public function cartBookingTW(Request $request)
    {
        // // dd($request->all());
        // $this->token = $request->bearerToken();
        // // dd($this->token);
        // $bookurl = "https://tourapidev.pulo1000.com/v2/cartBookingTour";
        // $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
        // $response = $post->post($bookurl, $request->all());
        // $result = json_decode(json_encode($response->json()));
        // dd($result);
        $tw = TiketWisata::find($request->tw_id);
        $customer = Auth::user();
        if ($tw->create_user == $customer->id) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Anda tidak dapat memesan Paket Anda sendiri',
                    'user' => $customer
                ]
            );
        }
        $media = MediaFile::find($tw->banner);
        $pre_url = $this->prod ?  'https://cdn.mykomodo.kabtour.com/uploads/' : 'https://cdn.mykomodo.kabtour.com/uploads/';
        $tw->banner = [
            "original" => $pre_url . $media->file_path,
            "200x150" => $media->file_resize_200 != null ? $pre_url . $media->file_resize_200 : null,
            "250x200" => $media->file_resize_250 != null ? $pre_url . $media->file_resize_250 : null,
            "400x350" => $media->file_resize_400 != null ? $pre_url . $media->file_resize_400 : null,
        ];
        // $tour_parent = TourParent::find($request->parent_id);

        $price = $request->price;
        $total = $request->total_price;
        $total_guests = $request->total_guests ?? 1;

        $start_date = new \DateTime($request->start_date);
        $end_date = new \DateTime($request->end_date);

        $admin_fee = $request->admin_fee ?? getAdminFee(intval($price) * intval($total_guests));
        // if (!$is_private) {
        //     $admin_fee = $admin_fee * $total_guests;
        // }

        $list_buyer_fees = json_encode(['admin_fee' => $admin_fee, 'transfer_fee' => 4440]);
        // $start_date = new \DateTime($request->start_date);

        $data_detail = [];
        $data_detail['tiket-wisata'] = $tw;
        // $data_detail['tour_parent'] = $tour_parent;
        $data_detail['datestart'] = $start_date->format('Y-m-d H:i:s');
        $data_detail['total_guests'] = $request->total_guests;
        // $start_date->modify('+ ' . max(1, $tour->duration) . ' hours');
        $data_detail['dateend'] = $end_date->format('Y-m-d H:i:s');
        // $data_detail['locname'] = Location::find($tour_parent->location_id)->name;
        $data_detail['locname'] = Location::find($tw->location_id)->name;

        $booking = new Booking();
        $booking->code = $this->generateTWCode();
        $booking->app_id = $request->app_id ?? $this->app_id;
        $booking->status = 'waiting';
        $booking->object_id = $tw->id;
        $booking->object_model = 'tiket-wisata';
        $booking->vendor_id = $tw->create_user;
        $booking->customer_id = $customer->id;
        $booking->total = intval($total) + $admin_fee;
        $booking->data_detail = json_encode($data_detail);
        $booking->facilities_detail = NULL;
        $booking->total_guests = $total_guests ?? 1;
        $booking->start_date = $data_detail['datestart'];
        $booking->end_date = $data_detail['dateend'];
        $booking->create_user = $customer->id;
        $booking->vendor_service_fee_amount = 0.00;
        $booking->vendor_service_fee = 0.00;
        $booking->buyer_fees = $list_buyer_fees ?? '';
        $booking->total_before_fees = $total;
        $booking->first_name = $request->first_name ?? $customer->first_name;
        $booking->last_name = $request->last_name ?? $customer->last_name;
        $booking->email = $request->email ?? $customer->email;
        $booking->address = $request->line1 ?? $request->address ?? $customer->address;
        // $booking->address2 = $request->line2 ?? $request->address2 ?? $customer->address;
        $booking->country = $request->country ?? $customer->country;
        $booking->zip_code = $request->zipcode ?? $customer->zip_code;
        $booking->city = $request->city ?? $customer->city;
        $booking->state = $request->province ?? $customer->state;
        $booking->phone = $request->phone ?? $customer->phone;
        $booking->gateway = 'xendit';
        $booking->status = 'waiting';
        $booking->save();

        // return response()->json(
        //     [
        //         'success' => true,
        //         'message' => 'Pesanan anda telah di buat, silahkan selesaikan proses pembayaran',
        //         'object_data' => $data_detail,
        //         'booking_data' => $booking
        //     ]
        // );
        return $this->updateBookingStatus($request, $booking->code);
    }

    public function generateTWCode()
    {
        $code = 'W-2-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        if (Booking::where('code', $code)->doesntExist())
            return $code;
        $this->generateTWCode();
    }

    public function updateBookingStatus(Request $request, $code)
    {
        $response = [
            'success' => false,
            'message' => ''
        ];
        $temp_booking = Booking::where('code', $code)->first();
        if ($temp_booking) {
            $booking = Booking::find($temp_booking->id);
            $booking->status = $request->status ?? 'waiting';
            if ($request->has('gateway')) {
                $booking->gateway = $request->gateway;
                $temp_fee = json_decode($booking->buyer_fees);
                $temp_total = 0;
                if ($booking->object_model == 'boat') {
                    $temp_total = $booking->total_before_fees + $temp_fee->admin_fee->dewasa + $temp_fee->admin_fee->anak + $temp_fee->transfer_fee;
                }
                // if($booking->object_model=='hotel'){
                //     $temp_total = $booking->total_before_fees + $temp_fee->admin_fee + $temp_fee->transfer_fee;
                // }
                // if($booking->object_model=='tour'){
                //     $temp_total = $booking->total_before_fees + $temp_fee->admin_fee + $temp_fee->transfer_fee;
                // }
                elseif ($booking->object_model == 'food-beverages') {
                    if ($request->has('shipping_cost')) {
                        $temp_fee->shipping_cost = intval($request->shipping_cost);
                        $booking->buyer_fees = json_encode($temp_fee);
                    }
                    $temp_total = $booking->total_before_fees + $temp_fee->admin_fee + $temp_fee->transfer_fee + $temp_fee->shipping_cost;
                } elseif ($booking->object_model == 'umkm') {
                    if ($request->has('shipping_cost')) {
                        $temp_fee->shipping_cost = intval($request->shipping_cost);
                        $booking->buyer_fees = json_encode($temp_fee);
                    }
                    $temp_total = $booking->total_before_fees + $temp_fee->admin_fee + $temp_fee->transfer_fee + $temp_fee->shipping_cost;
                } else {
                    $temp_total = $booking->total_before_fees + $temp_fee->admin_fee + $temp_fee->transfer_fee;
                }
                $booking->total = $temp_total;
            }
            $booking->save();

            if (substr($code, 0, 1) == 'F') {
                if (substr($code, 0, 2) == 'FB') {
                    $this->updateOrderStatus($request, $booking->code, null);
                }
            }

            if (substr($code, 0, 1) == 'U') {
                $this->updateOrderStatus($request, $booking->code, null);
            }

            $this->token = $request->bearerToken();
            $payurl = $this->prod ? "https://pg.kabtour.com/v2/xendit/create-payment/" . $code : "https://pg.kabtour.com/v2/xendit/create-payment/" . $code;
            $pay = json_decode(
                json_encode(
                    Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson()->get($payurl)->json()
                )
            );
            // dd($pay);
            $booking->payment_id = $pay->data->id;
            $booking->save();
            if (substr($code, 0, 1) == 'B') {
                $this->sendBoatConfirmationMail($booking);
            }
            if (substr($code, 0, 1) == 'H') {
                $this->sendHotelConfirmationMail($booking);
            }
            if (substr($code, 0, 1) == 'T') {
                $this->sendTourConfirmationMail($booking);
            }
            if (substr($code, 0, 1) == 'F') {
                if (substr($code, 0, 2) == 'FB') {
                    $this->updateOrderStatus($request, $booking->code, $pay->data);
                    $order = Order::find($booking->object_id);
                    $this->sendFBConfirmationMail($order);
                }
            }
            $response['success'] = true;
            $response['message'] = 'Kode booking telah diperbarui';
            $response['object_data'] = json_decode($booking->data_detail);
            $response['booking_data'] = $booking;
            $response['payment_data'] = $pay;
        } else {
            $response['success'] = false;
            $response['message'] = 'Kode booking tidak ada';
            $response['booking_data'] = null;
            $response['payment_data'] = null;
        }
        // dd($response);
        return response()->json($response);
    }

    public function updateOrderStatus(Request $request, $code, $payment = null)
    {
        $temp_order = Order::where('code_booking', $code)->first();
        if ($temp_order) {
            $order = Order::find($temp_order->id);
            // dd($order);
            if ($payment == null) {
                $order->status = $request->status ?? 'waiting';
                if ($request->has('gateway')) {
                    $order->mode_pay = $request->gateway;
                    // $temp_fee = json_decode($order->buyer_fees);
                    $temp_total = $order->total + 4440;
                    $order->total = $temp_total;
                }
                if ($request->has('shipping_type')) {
                    $order->shiptype = $request->shipping_type;
                }
                if ($request->has('firstname') || $request->has('first_name')) {
                    $order->firstname = $request->firstname ?? $request->first_name;
                }
                if ($request->has('lastname') || $request->has('last_name')) {
                    $order->firstname = $request->lastname ?? $request->last_name;
                }
                if ($request->has('phone')) {
                    $mobile = $request->phone;
                    if (substr($mobile, 0, 1) == '0') {
                        $temp_phone = substr($mobile, 1);
                        $mobile = '62' . $temp_phone;
                    }
                    $order->mobile = $mobile;
                }
                if ($request->has('line1')) {
                    $order->line1 = $request->line1;
                }
                if ($request->has('line2')) {
                    $order->line2 = $request->line2;
                }
                if ($request->has('latitude')) {
                    $order->latitude = $request->latitude;
                }
                if ($request->has('longitude')) {
                    $order->longitude = $request->longitude;
                }
                if ($request->has('notes')) {
                    $order->notes = $request->notes;
                }
                $order->save();

                $orderItems = OrderItem::where('code_booking', $code)->get();

                foreach ($orderItems as $orderItem) {
                    $item = OrderItem::find($orderItem->id);
                    $item->mode_pay = $order->mode_pay;
                    $item->ship_address = $order->line1;
                    $item->shiptype = $order->shiptype;
                    // $item->payment_id = $order->payment_id;
                    $item->save();
                }
            } else {
                $order->payment_id = $payment->id;
                $order->save();

                $orderItems = OrderItem::where('code_booking', $code)->get();

                foreach ($orderItems as $orderItem) {
                    $item = OrderItem::find($orderItem->id);
                    // $item->mode_pay = $order->mode_pay;
                    // $item->ship_address = $order->line1;
                    // $item->shiptype = $order->shiptype;
                    $item->payment_id = $order->payment_id;
                    $item->save();
                }
            }
        }
    }

    public function testSendMail($code)
    {
        $checkBooking = Booking::where('code', $code)->first();
        if ($checkBooking) {
            $booking = Booking::find($checkBooking->id);
            if (substr($code, 0, 1) == 'B') {
                $this->sendBoatConfirmationMail($booking);
            }
            if (substr($code, 0, 1) == 'H') {
                $this->sendHotelConfirmationMail($booking);
            }
            if (substr($code, 0, 1) == 'T') {
                $this->sendTourConfirmationMail($booking);
            }
            if (substr($code, 0, 1) == 'F') {
                $order = Order::find($booking->object_id);
                dd([$booking->object_id, $order]);
                Mail::to($booking->email)->send(new OrderMail($order));
            }
        }
    }

    public function sendBoatConfirmationMail($booking)
    {
        Mail::to($booking->email)->send(new OrderBoatMail($booking));
    }

    public function sendHotelConfirmationMail($booking)
    {
        Mail::to($booking->email)->send(new OrderBookMail($booking));
    }

    public function sendTourConfirmationMail($booking)
    {
        Mail::to($booking->email)->send(new OrderTourMail($booking));
    }

    public function sendFBConfirmationMail($order)
    {
        // $order = Order::find($booking->object_id)->with(['orderItem']);
        Mail::to($order->email)->send(new OrderMail($order));
    }

    public function getBookingByCode($code)
    {
        $response = [
            "success" => true,
            "message" => "Booking data fetched",
            "data" => ""
        ];

        $booking = Booking::where('vendor_id', Auth::id())->where('code', $code)->first();
        if (!$booking) {
            $response['success'] = false;
            $response['message'] = "Booking data not found";
        }
        $response['data'] = $booking;
        return response()->json($response);
    }

    public function getTransactions(Request $request)
    {
        $user = Auth::user();
        // $role_id = DB::table('core_model_has_roles')->where('model_id', Auth::id())->first()->role_id ?? 0;
        $role_id = $user->role_id;
        // dd($role_id);
        $app_id = $request->app_id ?? $this->app_id;
        $response = [
            "success" => true,
            "message" => "Booking data fetched",
            "data" => []
        ];
        $object_ids = [];
        if ($role_id == 7) {
            $getMyBoats = Boat::where('create_user', Auth::id())->whereNull(['parent_id', 'agent_id'])->get();
            foreach ($getMyBoats as $k) {
                $getChildBoats = Boat::where('parent_id', $k->id)->get();
                foreach ($getChildBoats as $ck) {
                    array_push($object_ids, $ck->id);
                }
            }
            // dd($object_ids);
            $response["data"] = Booking::where('app_id',$app_id)->where('object_model', 'boat')->whereIn('object_id', $object_ids)->whereNotIn('status', ['draft', 'waiting'])->orderBy('id', 'desc')->get();
        } elseif ($role_id == 2) {
            // $temp = [];
            // $booking = Booking::where('customer_id',Auth::id())->whereNotIn('status',['draft','Selesai'])->orderBy('created_at','desc')->get();
            // $order = Order::where('user_id',Auth::id())->whereNotIn('status',['draft','Selesai'])->orderBy('created_at','desc')->get();
            // // $response["data"]= array_merge(
            // //         json_decode($booking, true),
            // //         json_decode($order, true)
            // //     );
            // //     usort($info, function ($a, $b) {
            // //         return $a['created_at'] <=> $b['created_at'];
            // //     });
            // //     dd($response["data"]);
            // // foreach($response["data"] as $item){
            // //     $item['created_at'];
            // // }
            // foreach($booking as $bk){
            //     $temp[]=json_decode($bk,true);
            // }
            // foreach($order as &$ok){
            //     $ok->code = $ok->code_booking;
            //     $temp[]=json_decode($ok,true);
            // }
            // usort($temp, function ($a, $b) {
            //     return [$a['created_at']] <=> [$b['created_at']];
            // });
            // $response['data']=$temp;
            $booking = Booking::where('app_id',$app_id)->where('customer_id', Auth::id())->whereNotIn('status', ['draft', 'Selesai', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
            $response['data'] = $booking;
        } elseif ($role_id == 1) {
            // $temp = [];
            // $booking = Booking::where('vendor_id',Auth::id())->whereNotIn('status',['draft','Selesai'])->orderBy('created_at','desc')->get();
            // $order = Order::where('user_id',Auth::id())->whereNotIn('status',['draft','Selesai'])->orderBy('created_at','desc')->get();
            // $product_ids = [];
            // $get_product_ids = Product::where('create_user',Auth::id())->get('id');
            // foreach($get_product_ids as $pid){
            //     $product_ids[] = $pid->id;
            // }
            // $order = OrderItem::whereIn('product_id',$product_ids)->whereNotIn('status',['draft','Selesai'])->orderBy('created_at','desc')->get();
            // foreach($booking as $bk){
            //     $temp[]=json_decode($bk,true);
            // }
            // foreach($order as &$ok){
            //     $ok->code = $ok->code_booking;
            //     $temp[]=json_decode($ok,true);
            // }
            // usort($temp, function ($a, $b) {
            //     return [$a['created_at']] <=> [$b['created_at']];
            // });
            // $response['data']=$temp;
            $booking = Booking::where('app_id',$app_id)->where('vendor_id', Auth::id())->whereNotIn('status', ['draft', 'Selesai', 'waiting', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
            $response['data'] = $booking;
        } elseif ($role_id == 8) {
            $booking = Booking::where('app_id',$app_id)->where('courier_id', Auth::id())->whereNotIn('status', ['draft', 'Selesai', 'waiting', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
            $response['data'] = $booking;
        // }elseif ($role_id = 3){
        //     $response["data"] = Booking::whereNotIn('status', ['draft', 'Selesai', 'waiting', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name', 'vendor:id,first_name,last_name,name', 'courier:id,first_name,last_name,name'])->get();
        // }elseif ($role_id=4){
        //     $response["data"] = Booking::whereNotIn('status', ['draft', 'Selesai', 'waiting', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name', 'vendor:id,first_name,last_name,name', 'courier:id,first_name,last_name,name'])->get();
        // }elseif ($role_id = 99){
        //     $response["data"] = Booking::whereNotIn('status', ['draft', 'Selesai', 'waiting', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name', 'vendor:id,first_name,last_name,name', 'courier:id,first_name,last_name,name'])->get();
        // }
        } elseif ($role_id == 11) {
            $booking = Booking::where('app_id',$app_id)->where('vendor_id', Auth::id())->whereNotIn('status', ['draft', 'Selesai', 'waiting', 'Payment Expired', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
            $response['data'] = $booking;
        }elseif($role_id == 3 || $role_id == 4 || $role_id == 99){
            $query = Booking::query();
            $query->where('app_id',$app_id);
            if($request->has('object_model')){
                $query->where('object_model',$request->object_model);
            }
            if($request->has('object_id')){
                $query->where('object_id',$request->object_id);
            }
            if($request->has('vendor_id')){
                $query->where('vendor_id',$request->vendor_id);
            }
            $response['data'] = $query->orderBy('id', 'desc')->with(['payment', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
        }
        return response()->json($response);
    }

    public function transactionDetail(Request $request, $code)
    {
        $result = [
            'success' => false,
            'message' => 'nok',
            'data' => ''
        ];
        $user = Auth::user();
        $role_id = 0;
        if (!$request->has('role_id')) {
            // $role_id = DB::table('core_model_has_roles')->where('model_id', Auth::id())->first()->role_id;
            $role_id = $user->role_id;
        } else {
            $role_id = $request->role_id;
        }

        $data = Booking::where('code', $code)->first();
        if ($data) {
            if ($role_id == 7) {
                $data_detail = json_decode($data->data_detail);
                $parent_id = $data_detail->parent_id;
                $boat = Boat::find($parent_id);
                if ($boat) {
                    if ($boat->create_user === Auth::id()) {
                        if ($data->payment_id) {
                            $data->payment = Payment::find($data->payment_id);
                            $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                        }
                        $result['data'] = $data->toArray();
                        $result['message'] = 'Data ditemukan';
                        $result['success'] = true;
                    } else {
                        $result['success'] = false;
                        $result['message'] = 'Data tidak ditemukan';
                        $result['error'] = 404;
                    }
                }
            }
            if ($role_id == 1) {
                if ($data->object_model == 'hotel') {
                    $data_detail = json_decode($data->data_detail);
                    $hotel = Hotel::find($data->object_id);
                    // dd($hotel);
                    if ($hotel) {
                        if ($hotel->create_user === Auth::id()) {
                            if ($data->payment_id) {
                                $data->payment = Payment::find($data->payment_id);
                                $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                            }
                            // $data->room = HotelRoomBooking::where('booking_id',$data->id)->get();
                            $result['data'] = $data->toArray();
                            $result['message'] = 'Data ditemukan';
                            $result['success'] = true;
                        } else {
                            $result['success'] = false;
                            $result['message'] = 'Data tidak ditemukan';
                            $result['error'] = 404;
                        }
                    } else {
                        $getPayment = Payment::where('code', $data->code)->first();
                        if ($getPayment) {
                            $data->payment = $getPayment;
                            $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                        }
                        $result['data'] = $data->toArray();
                        $result['message'] = 'Data ditemukan';
                        $result['success'] = true;
                    }
                } elseif ($data->object_model == 'tour') {
                    $data_detail = json_decode($data->data_detail);
                    $tour = Tour::find($data->object_id);
                    if ($tour) {
                        if ($tour->create_user === Auth::id()) {
                            if ($data->payment_id) {
                                $data->payment = Payment::find($data->payment_id);
                                $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                            }
                            // $data->room = HotelRoomBooking::where('booking_id',$data->id)->get();
                            $result['data'] = $data->toArray();
                            $result['message'] = 'Data ditemukan';
                            $result['success'] = true;
                        } else {
                            $result['success'] = false;
                            $result['message'] = 'Data tidak ditemukan';
                            $result['error'] = 404;
                        }
                    } else {
                        $getPayment = Payment::where('code', $data->code)->first();
                        if ($getPayment) {
                            $data->payment = $getPayment;
                            $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                        }
                        $result['data'] = $data->toArray();
                        $result['message'] = 'Data ditemukan';
                        $result['success'] = true;
                    }
                } else {
                    // $data->data_detail = json_decode($data->data_detail);
                    $data->order = Order::find($data->object_id);
                    // dd([$data]);
                    if ($data->payment_id) {
                        $data->payment = Payment::find($data->payment_id);
                        $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                    }
                    $result['data'] = $data->toArray();
                    $result['message'] = 'Data ditemukan';
                    $result['success'] = true;
                }
            }
            if ($role_id == 8) {
                // $data->data_detail = json_decode($data->data_detail);
                $data->order = Order::find($data->object_id);
                // dd([$data]);
                if ($data->payment_id) {
                    $data->payment = Payment::find($data->payment_id);
                    $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                }
                $result['data'] = $data->toArray();
                $result['message'] = 'Data ditemukan';
                $result['success'] = true;
            }
            if ($role_id == 2) {
                if ($data->object_model == 'food-beverages') {
                    $data->order = Order::find($data->object_id);
                    // dd([$data]);
                    if ($data->payment_id) {
                        $data->payment = Payment::find($data->payment_id);
                        $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                    }
                } else {
                    if ($data->payment_id) {
                        $data->payment = Payment::find($data->payment_id);
                        $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                    }
                }
                $result['data'] = $data->toArray();
                $result['message'] = 'Data ditemukan';
                $result['success'] = true;
            }
        }
        return response()->json($result);
    }


    public function getHistoryTransactions(Request $request)
    {
        // Mendapatkan role_id dari user yang sedang login
        // $role_id = DB::table('core_model_has_roles')->where('model_id', Auth::id())->first()->role_id;
        $user = Auth::user();
        $role_id = $user->role_id;
        $app_id = $request->app_id ?? $this->app_id;
        $response = [
            "success" => true,
            "message" => "Booking data fetched",
            "data" => []
        ];
        // dd($role_id);

        switch ($role_id) {
            case 7:
                $object_ids = [];
                // Mendapatkan data boat yang dibuat oleh user yang login dan tidak memiliki parent_id atau agent_id
                $getMyBoats = Boat::where('create_user', Auth::id())->whereNull(['parent_id', 'agent_id'])->get();
                foreach ($getMyBoats as $k) {
                    // Mendapatkan data boat yang memiliki parent_id sesuai dengan data boat yang dibuat oleh user yang login
                    $getChildBoats = Boat::where('parent_id', $k->id)->get();
                    foreach ($getChildBoats as $ck) {
                        // Menambahkan id dari data boat ke dalam array object_ids
                        array_push($object_ids, $ck->id);
                    }
                }
                // Mendapatkan data booking yang memiliki object_id yang terdapat pada array object_ids dan memiliki status 'Selesai'
                $response["data"] = Booking::where('app_id',$app_id)->whereIn('object_id', $object_ids)->where('status', 'Selesai')->orderBy('id', 'desc')->with(['review'])->get();
                break;
            case 2:
                // Mendapatkan data booking yang memiliki customer_id sesuai dengan user yang login dan memiliki status 'Selesai'
                $response["data"] = Booking::where('app_id',$app_id)->where('customer_id', Auth::id())->whereIn('status', ['Selesai', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
                break;
            case 1:
                // Mendapatkan data booking yang memiliki vendor_id sesuai dengan user yang login dan memiliki status 'Selesai'
                $response["data"] = Booking::where('app_id',$app_id)->where('vendor_id', Auth::id())->whereIn('status', ['Selesai', 'Dibatalkan'])->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
                break;
            case 8:
                // Mendapatkan data booking yang memiliki vendor_id sesuai dengan user yang login dan memiliki status 'Selesai'
                $response["data"] = Booking::where('app_id',$app_id)->where('courier_id', Auth::id())->where('status', 'Selesai')->orderBy('id', 'desc')->with(['review', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
                break;
            case 3:
                $response["data"] = Booking::where('app_id',$app_id)->where('status', 'Selesai')->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
                break;
            case 99:
                $response["data"] = Booking::where('app_id',$app_id)->where('status', 'Selesai')->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
                break;
            case 4:
                $response["data"] = Booking::where('app_id',$app_id)->where('status', 'Selesai')->orderBy('id', 'desc')->with(['payment', 'review', 'customer:id,first_name,last_name,name,email', 'vendor:id,first_name,last_name,name,email', 'courier:id,first_name,last_name,name,email'])->get();
                break;
            default:
                $response["data"] = [];
                break;
        }

        return response()->json($response);
    }


    public function historyTransactionDetail(Request $request, $code)
    {
        $result = [
            'success' => false,
            'message' => 'nok',
            'function' => 'detail',
            'data' => ''
        ];
        $user = Auth::user();
        $role_id = 0;
        if (!$request->has('role_id')) {
            // $role_id = DB::table('core_model_has_roles')->where('model_id', Auth::id())->first()->role_id;
            $role_id = $user->role_id;
        } else {
            $role_id = $request->role_id;
        }

        $data = Booking::where('code', $code)->first();
        $data->review = BravoReview::where('booking_code', $code)->first();
        if ($data) {
            if ($role_id == 7) {
                $data_detail = json_decode($data->data_detail);
                $parent_id = $data_detail->parent_id;
                $boat = Boat::find($parent_id);
                if ($boat) {
                    if ($boat->create_user === Auth::id()) {
                        if ($data->payment_id) {
                            $data->payment = Payment::find($data->payment_id);
                            $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                        }
                        $result['data'] = $data->toArray();
                        $result['message'] = 'Data ditemukan';
                        $result['success'] = true;
                    } else {
                        $result['success'] = false;
                        $result['message'] = 'Data tidak ditemukan';
                        $result['error'] = 404;
                    }
                }
            }
            if ($role_id == 1) {
                if ($data->object_model == 'hotel') {
                    $data_detail = json_decode($data->data_detail);
                    $hotel = Hotel::find($data->object_id);
                    if ($hotel) {
                        if ($hotel->create_user === Auth::id()) {
                            if ($data->payment_id) {
                                $data->payment = Payment::find($data->payment_id);
                                $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                            }
                            // $data->room = HotelRoomBooking::where('booking_id',$data->id)->get();
                            $result['data'] = $data->toArray();
                            $result['message'] = 'Data ditemukan';
                            $result['success'] = true;
                        } else {
                            $result['success'] = false;
                            $result['message'] = 'Data tidak ditemukan';
                            $result['error'] = 404;
                        }
                    } else {
                        $getPayment = Payment::where('code', $data->code)->first();
                        if ($getPayment) {
                            $data->payment = $getPayment;
                            $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                        }
                        $result['data'] = $data->toArray();
                        $result['message'] = 'Data ditemukan';
                        $result['success'] = true;
                    }
                } elseif ($data->object_model == 'tour') {
                    $data_detail = json_decode($data->data_detail);
                    $tour = Tour::find($data->object_id);
                    if ($tour) {
                        if ($tour->create_user === Auth::id()) {
                            if ($data->payment_id) {
                                $data->payment = Payment::find($data->payment_id);
                                $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                            }
                            // $data->room = HotelRoomBooking::where('booking_id',$data->id)->get();
                            $result['data'] = $data->toArray();
                            $result['message'] = 'Data ditemukan';
                            $result['success'] = true;
                        } else {
                            $result['success'] = false;
                            $result['message'] = 'Data tidak ditemukan';
                            $result['error'] = 404;
                        }
                    } else {
                        $getPayment = Payment::where('code', $data->code)->first();
                        if ($getPayment) {
                            $data->payment = $getPayment;
                            $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                        }
                        $result['data'] = $data->toArray();
                        $result['message'] = 'Data ditemukan';
                        $result['success'] = true;
                    }
                } else {
                    // $data->data_detail = json_decode($data->data_detail);
                    $data->order = Order::find($data->object_id);
                    // dd([$data]);
                    if ($data->payment_id) {
                        $data->payment = Payment::find($data->payment_id);
                        $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                    }
                    $result['data'] = $data->toArray();
                    $result['message'] = 'Data ditemukan';
                    $result['success'] = true;
                }
            }
            if ($role_id == 8) {
                $data->order = Order::find($data->object_id);
                // dd([$data]);
                if ($data->payment_id) {
                    $data->payment = Payment::find($data->payment_id);
                    $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                }
                $result['data'] = $data->toArray();
                $result['message'] = 'Data ditemukan';
                $result['success'] = true;
            }
            if ($role_id == 2) {
                if ($data->object_model == 'food-beverages') {
                    $data->order = Order::find($data->object_id);
                    // dd([$data]);
                    if ($data->payment_id) {
                        $data->payment = Payment::find($data->payment_id);
                        $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                    }
                } else {
                    if ($data->payment_id) {
                        $data->payment = Payment::find($data->payment_id);
                        $data->payment_channel = PaymentChannel::where('code', $data->gateway)->first();
                    }
                }
                $result['data'] = $data->toArray();
                $result['message'] = 'Data ditemukan';
                $result['success'] = true;
            }
        }
        return response()->json($result);
    }

    public function updateTransactionStatus(Request $request, $code)
    {
        $response = [
            'success' => false,
            'message' => ''
        ];
        $user = Auth::user();
        // $role_id = DB::table('core_model_has_roles')->where('model_id', Auth::id())->first()->role_id;
        $role_id = $user->role_id;
        // dd($role_id);
        $temp_booking = Booking::where('code', $code)->first();
        if ($temp_booking) {
            $booking = Booking::find($temp_booking->id);
            $booking->status = $request->status;
            // if($request->has('courier_id')){
            //     $booking->courier_id = $request->courier;
            // }
            $booking->save();
            $prefix = substr($code, 0, 1);
            $arrprefix = ['M', 'U', 'F', 'S'];
            if (in_array($prefix, $arrprefix)) {
                if($booking->object_model == 'umkm'){
                    $dataDetail = json_decode($booking->data_detail);
                    $receipt_number = date('YmdHis').$request->courier_id.''.$booking->code;
                    $dataDetail->shipping = json_decode(json_encode(['courier'=>$request->courier_id,'shipping_date'=>date('Y-m-d H:i:s'),'delivered_at'=>null,'receipt_number'=>$receipt_number]));
                    $booking->data_detail = json_encode($dataDetail);
                }else{
                    $booking->courier_id = $request->courier_id ?? $booking->courier_id;
                }
                $booking->save();
                $temp_order = Order::where('code_booking', $code)->first();
                if ($temp_order) {
                    $order = Order::find($temp_order->id);
                    $order->status = $request->status;
                    $order->status_package = $request->status == 'Proses' ? 'Packaging' : 'Waiting';
                    $order->status_shipping = $request->status == 'Proses' ? 'Shipment' : 'Waiting';
                    $order->status_delivery = $request->status == 'Partial' ? 'Delivered' : 'Waiting';
                    $order->courier_id = $request->courier_id ?? $order->courier_id;
                    $order->save();
                }
            }
            if ($role_id != 2) {
                if ($booking->status == 'Proses') {
                    $this->notif['headings'] = "Pesanan Diproses";
                    $this->notif['message'] = "Pesanan Anda dengan kode " . $booking->code . " sudah diproses oleh Mitra";
                    $this->notif['type'] = "Process";
                    $this->notif['notif_type'] = ["in_app", "push"];
                    $wisatawan = User::find($booking->customer_id);
                    $this->notif['target'] = $wisatawan->id;
                    $player_ids = explode(',', $wisatawan->player_id);
                    foreach ($player_ids as $k => $v) {
                        $this->notif['player_ids'][] = $v;
                    }
                    $this->notif['target_name'] = ucfirst($wisatawan->first_name) . ' ' . ucfirst($wisatawan->last_name);
                    $this->notif['link'] = $this->wurl . '/user/order/detail/' . $booking->id;
                    $this->notif['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->notif['order'] = $booking;
                    $this->notif['mail_target_type'] = "M";
                    $pay = Payment::find($booking->payment_id);
                    $response['success'] = true;
                    $response['message'] = 'Your Booking has been updated';
                    $response['booking_data'] = $booking;
                    $response['payment_data'] = $pay;
                    $this->sendNotif($this->notif);
                } elseif ($booking->status == 'Dibatalkan') {
                    $this->notif['headings'] = "Pesanan Dibatalkan";
                    $this->notif['message'] = "Pesanan Anda dengan kode " . $booking->code . " telah Dibatalkan";
                    $this->notif['type'] = "Cancelled";
                    $this->notif['notif_type'] = ["in_app", "push"];
                    $wisatawan = User::find($booking->customer_id);
                    $this->notif['target'] = $wisatawan->id;
                    $player_ids = explode(',', $wisatawan->player_id);
                    foreach ($player_ids as $k => $v) {
                        $this->notif['player_ids'][] = $v;
                    }
                    $this->notif['target_name'] = ucfirst($wisatawan->first_name) . ' ' . ucfirst($wisatawan->last_name);
                    $this->notif['link'] = $this->wurl . '/user/order/detail/' . $booking->id;
                    $this->notif['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->notif['order'] = $booking;
                    $this->notif['mail_target_type'] = "M";
                    $pay = Payment::find($booking->payment_id);
                    $fees = json_decode($booking->buyer_fees);
                    $shippingCost = $fees->shipping_cost ?? 0;
                    $amount = $booking->total_before_fees+$shippingCost;
                    $wallet = DB::table('user_wallets')->where('holder_id', $booking->customer_id)->first();
                    $balance = intval($wallet->balance) + intval($amount);
                    DB::table('user_wallets')
                        ->where('holder_id', $booking->customer_id)
                        ->update(['balance' => $balance, 'updated_at' => $booking->updated_at, 'update_user' => $booking->customer_id]);
                    $response['success'] = true;
                    $response['message'] = 'Pesanan Anda telah dibatalkan';
                    $response['booking_data'] = $booking;
                    $response['payment_data'] = $pay;
                    $this->sendNotif($this->notif);
                } elseif ($booking->status == 'Menunggu Kurir') {
                    $this->notif['headings'] = "Menunggu Kurir (" . $booking->code . ")";
                    $this->notif['message'] = "Kurir sedang mengambil Pesanan Anda";
                    $this->notif['type'] = "Menunggu Kurir";
                    $this->notif['notif_type'] = ["in_app", "push"];
                    $wisatawan = User::find($booking->customer_id);
                    $this->notif['target'] = $wisatawan->id;
                    $player_ids = explode(',', $wisatawan->player_id);
                    foreach ($player_ids as $k => $v) {
                        $this->notif['player_ids'][] = $v;
                    }
                    $this->notif['target_name'] = ucfirst($wisatawan->first_name) . ' ' . ucfirst($wisatawan->last_name);
                    $this->notif['link'] = $this->wurl . '/user/order/detail/' . $booking->id;
                    $this->notif['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->notif['order'] = $booking;
                    $this->notif['mail_target_type'] = "M";

                    $pay = Payment::find($booking->payment_id);
                    $response['success'] = true;
                    $response['message'] = 'Pesanan Anda telah diperbarui';
                    $response['booking_data'] = $booking;
                    $response['payment_data'] = $pay;
                    $this->sendNotif($this->notif);

                    $courier = User::find($booking->courier_id);
                    $this->notif_mitra['headings'] = "Satu Antaran Baru";
                    $this->notif_mitra['message'] = "Ada satu pesanan untuk diantar";
                    $this->notif_mitra['type'] = "courier_order";
                    $this->notif_mitra['notif_type'] = ["in_app", "push"];
                    // $this->notif_mitra['targets'][] = $mitra->id;
                    $this->notif_mitra['targets'][] = $courier->id;
                    $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $courier->id;
                    // $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $owner->id;
                    $this->notif_mitra['target_names'][] = ucfirst($courier->first_name) . ' ' . ucfirst($courier->last_name);
                    // $this->notif_mitra['target_names'][] = ucfirst($owner->first_name) . ' ' . ucfirst($owner->last_name);
                    $player_id_mitra = explode(',', $courier->player_id);
                    foreach ($player_id_mitra as $key => $val) {
                        if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                            $this->notif_mitra['player_ids'][] = $val;
                        }
                    }
                    // foreach ($player_id_owner as $key => $val) {
                    //     if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                    //         $this->notif_mitra['player_ids'][] = $val;
                    //     }
                    // }
                    $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->sendNotif($this->notif_mitra);
                } elseif ($booking->status == 'Sedang Diantar' || $booking->status == 'Shipping') {
                    $this->notif['headings'] = "Sedang Diantar (" . $booking->code . ")";
                    $this->notif['message'] = "Kurir sedang mengantar Pesanan Anda";
                    $this->notif['type'] = "Antar";
                    $this->notif['notif_type'] = ["in_app", "push"];
                    $wisatawan = User::find($booking->customer_id);
                    $this->notif['target'] = $wisatawan->id;
                    $player_ids = explode(',', $wisatawan->player_id);
                    foreach ($player_ids as $k => $v) {
                        $this->notif['player_ids'][] = $v;
                    }
                    $this->notif['target_name'] = ucfirst($wisatawan->first_name) . ' ' . ucfirst($wisatawan->last_name);
                    $this->notif['link'] = $this->wurl . '/user/order/detail/' . $booking->id;
                    $this->notif['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->notif['order'] = $booking;
                    $this->notif['mail_target_type'] = "M";
                    $pay = Payment::find($booking->payment_id);
                    $response['success'] = true;
                    $response['message'] = 'Pesanan anda telah diperbarui';
                    $response['booking_data'] = $booking;
                    $response['payment_data'] = $pay;
                    $this->sendNotif($this->notif);

                    $mitra = User::find($booking->vendor_id);
                    $this->notif_mitra['headings'] = "Sedang Diantar (" . $booking->code . ")";
                    $this->notif_mitra['message'] = "Kurir sedang mengantar pesanan";
                    $this->notif_mitra['type'] = "mitra_order";
                    $this->notif_mitra['notif_type'] = ["in_app", "push"];
                    $this->notif_mitra['targets'][] = $mitra->id;
                    $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $mitra->id;
                    $this->notif_mitra['target_names'][] = ucfirst($mitra->first_name) . ' ' . ucfirst($mitra->last_name);
                    $player_id_mitra = explode(',', $mitra->player_id);
                    foreach ($player_id_mitra as $key => $val) {
                        if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                            $this->notif_mitra['player_ids'][] = $val;
                        }
                    }
                    $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->sendNotif($this->notif_mitra);
                } elseif ($booking->status == 'Partial') {
                    $this->notif['headings'] = "Selesaikan Transaksi";
                    $this->notif['message'] = "Ayo selesaikan transaksi dengan Kode Pemesanan " . $booking->code . " ";
                    $this->notif['type'] = "Partial";
                    $this->notif['notif_type'] = ["in_app", "push"];
                    $wisatawan = User::find($booking->customer_id);
                    $this->notif['target'] = $wisatawan->id;
                    $player_ids = explode(',', $wisatawan->player_id);
                    foreach ($player_ids as $k => $v) {
                        $this->notif['player_ids'][] = $v;
                    }
                    $this->notif['target_name'] = ucfirst($wisatawan->first_name) . ' ' . ucfirst($wisatawan->last_name);
                    $this->notif['link'] = $this->wurl . '/user/order/detail/' . $booking->id;
                    $this->notif['channel'] = 'App\Notifications\PrivateChannelServices';
                    $this->notif['order'] = $booking;
                    $this->notif['mail_target_type'] = "M";
                    $pay = Payment::find($booking->payment_id);
                    $response['success'] = true;
                    $response['message'] = 'Pesanan anda telah diperbarui';
                    $response['booking_data'] = $booking;
                    $response['payment_data'] = $pay;
                    $this->sendNotif($this->notif);

                    if ($booking->object_model == 'food-beverages') {
                        $temp_order = Order::where('code_booking', $code)->first();
                        if ($temp_order) {
                            if ($temp_order->shiptype == 'Diantar') {
                                $mitra = User::find($booking->vendor_id);
                                $this->notif_mitra['headings'] = "Pengantaran Selesai (" . $booking->code . ")";
                                $this->notif_mitra['message'] = "Kurir telah selesai mengantar pesanan";
                                $this->notif_mitra['type'] = "mitra_order";
                                $this->notif_mitra['notif_type'] = ["in_app", "push"];
                                $this->notif_mitra['targets'][] = $mitra->id;
                                $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $mitra->id;
                                $this->notif_mitra['target_names'][] = ucfirst($mitra->first_name) . ' ' . ucfirst($mitra->last_name);
                                $player_id_mitra = explode(',', $mitra->player_id);
                                foreach ($player_id_mitra as $key => $val) {
                                    if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                                        $this->notif_mitra['player_ids'][] = $val;
                                    }
                                }
                                $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                                $this->sendNotif($this->notif_mitra);
                            }
                        }
                    }
                }
            } else {
                if ($booking->status == 'Selesai') {
                    $amount = $booking->total_before_fees;
                    $wallet = DB::table('user_wallets')->where('holder_id', $booking->vendor_id)->first();
                    $balance = intval($wallet->balance) + intval($amount);
                    DB::table('user_wallets')
                        ->where('holder_id', $booking->vendor_id)
                        ->update(['balance' => $balance, 'updated_at' => $booking->updated_at, 'update_user' => $booking->vendor_id]);
                    if ($booking->object_model == 'food-beverage' || $booking->object_model == 'food-beverages') {
                        if ($booking->courier_id) {
                            $fees = json_decode($booking->buyer_fees);
                            $shippingCost = $fees->shipping_cost;
                            $wallet = DB::table('user_wallets')->where('holder_id', $booking->courier_id)->first();
                            $balance = intval($wallet->balance) + intval($shippingCost);
                            DB::table('user_wallets')
                                ->where('holder_id', $booking->courier_id)
                                ->update(['balance' => $balance, 'updated_at' => $booking->updated_at, 'update_user' => $booking->courier_id]);
                        }
                    }
                    $this->notif_mitra['headings'] = "Transaksi Selesai";
                    $this->notif_mitra['message'] = "Transaksi dengan Kode Pemesanan " . $booking->code . " telah selesai";
                    $this->notif_mitra['type'] = "Finish";
                    $this->notif_mitra['notif_type'] = ["in_app", "push"];
                    if ($booking->object_model == 'boat') {
                        $boat = Boat::find($booking->object_id);
                        $mitra = User::find($boat->agent_id);
                        $player_id_mitra = explode(',', $mitra->player_id);
                        $owner = User::find($boat->create_user);
                        $player_id_owner = explode(',', $owner->player_id);
                        $this->notif_mitra['targets'][] = $mitra->id;
                        $this->notif_mitra['targets'][] = $owner->id;
                        $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $mitra->id;
                        $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $owner->id;
                        $this->notif_mitra['target_names'][] = ucfirst($mitra->first_name) . ' ' . ucfirst($mitra->last_name);
                        $this->notif_mitra['target_names'][] = ucfirst($owner->first_name) . ' ' . ucfirst($owner->last_name);
                        foreach ($player_id_mitra as $key => $val) {
                            if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                                $this->notif_mitra['player_ids'][] = $val;
                            }
                        }
                        foreach ($player_id_owner as $key => $val) {
                            if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                                $this->notif_mitra['player_ids'][] = $val;
                            }
                        }
                        $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                        $pay = Payment::find($booking->payment_id);
                        $response['success'] = true;
                        $response['message'] = 'Pesanan anda telah diperbarui';
                        $response['booking_data'] = $booking;
                        $response['payment_data'] = $pay;
                        $this->sendNotif($this->notif_mitra);
                    } else {
                        $mitra = User::find($booking->vendor_id);
                        $player_id_mitra = explode(',', $mitra->player_id);
                        $this->notif_mitra['targets'][] = $mitra->id;
                        $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $mitra->id;
                        $this->notif_mitra['target_names'][] = ucfirst($mitra->first_name) . ' ' . ucfirst($mitra->last_name);
                        foreach ($player_id_mitra as $key => $val) {
                            if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                                $this->notif_mitra['player_ids'][] = $val;
                            }
                        }
                        $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                        $pay = Payment::find($booking->payment_id);
                        $response['success'] = true;
                        $response['message'] = 'Pesanan anda telah diperbarui';
                        $response['booking_data'] = $booking;
                        $response['payment_data'] = $pay;
                        $this->sendNotif($this->notif_mitra);

                        if ($booking->object_model == 'food-beverages') {
                            $courier = User::find($booking->courier_id);
                            $player_id_courier = explode(',', $courier->player_id);
                            $this->notif_mitra['targets'][] = $courier->id;
                            $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $courier->id;
                            $this->notif_mitra['target_names'][] = ucfirst($courier->first_name) . ' ' . ucfirst($courier->last_name);
                            foreach ($player_id_courier as $key => $val) {
                                if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                                    $this->notif_mitra['player_ids'][] = $val;
                                }
                            }
                            $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                            $this->sendNotif($this->notif_mitra);
                        }
                    }
                } elseif ($booking->status == 'minta_batal') {
                    $this->notif_mitra['headings'] = "Permintaan Pembatalan";
                    $this->notif_mitra['message'] = "Permintaan Pembatalan untuk Kode Pemesanan " . $booking->code;
                    $this->notif_mitra['type'] = "Cancel Request";
                    $this->notif_mitra['notif_type'] = ["in_app", "push"];
                    $mitra = User::find($booking->vendor_id);
                    $player_id_mitra = explode(',', $mitra->player_id);
                    $this->notif_mitra['targets'][] = $mitra->id;
                    $this->notif_mitra['links'][] = $this->murl . '/admin/order/' . $mitra->id;
                    $this->notif_mitra['target_names'][] = ucfirst($mitra->first_name) . ' ' . ucfirst($mitra->last_name);
                    foreach ($player_id_mitra as $key => $val) {
                        if ($val && !empty($val) && !in_array($val, $this->notif_mitra['player_ids'])) {
                            $this->notif_mitra['player_ids'][] = $val;
                        }
                    }
                    $this->notif_mitra['channel'] = 'App\Notifications\PrivateChannelServices';
                    $pay = Payment::find($booking->payment_id);
                    $response['success'] = true;
                    $response['message'] = 'Permintaan pembatalan telah dikirim. Mohon menunggu konfirmasi Mitra';
                    $response['booking_data'] = $booking;
                    $response['payment_data'] = $pay;
                    $this->sendNotif($this->notif_mitra);
                }
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'Kode pemesanan tidak ada';
            $response['booking_data'] = null;
            $response['payment_data'] = null;
        }
        return response()->json($response);
    }

    public function sendNotif($data)
    {
        $send = new NotificationController();
        $send->sendNotif($data);
    }
}
