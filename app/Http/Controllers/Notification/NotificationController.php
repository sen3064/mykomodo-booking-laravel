<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use App\Models\NotificationPush;
use App\Http\Controllers\Notification\PushNotificationController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderBookMail;
use App\Mail\OrderBoatMail;
use App\Mail\OrderBoatMailNew;
use App\Mail\OrderMail;
use App\Models\Booking;

class NotificationController extends Controller
{
    //
    protected $prod = false;
    public $lws = 'https://nr.bismut.id';
    public static function getParam($role){
        return [
            'target' => '',
            'target_role' => strtolower($role ?? ''),
            'target_name' => '',
            'notif_type' => [],
            'channel' => '',
            'type' => '',
            'toAdmin' => 0,
            'link' => '',
            'headings' => '',
            'message' => '',
            'player_ids' => [],
            'order'=>'',
            'mail_target_type'=>'',
        ];
    }
    
    public function sendNotifAPI(Request $request){
        $this->sendNotif($request->notif_data);
        return response()->json([
            'success'=>true,
            'message'=>'In Queue',
            'req'=>$request->notif_data
        ]);
    }

    public function sendNotif($data){
        if(in_array("in_app",$data['notif_type'])){
            if(isset($data['targets'])){
                for($i=0;$i<sizeof($data['targets']);$i++){
                    $data['notif_id'] = $this->generateUUID();
                    $data['target'] = $data['targets'][$i];
                    $data['target_name'] = $data['target_names'][$i];
                    $data['link'] = $data['links'][$i];
                    $this->saveNotif($data);
                    $this->sendInAppNotif($data);
                }
            }else{
                $data['notif_id'] = $this->generateUUID();
                $this->saveNotif($data);
                $this->sendInAppNotif($data);
            }
        }
        if(in_array("push",$data['notif_type'])){
            $this->sendPushNotif($data);
        }
        if(in_array("mail",$data['notif_type'])){
            $this->sendMailNotif($data);
        }
    }
    
    public function generateUUID(){
        $notif_id = Str::orderedUuid();
        $check = NotificationPush::find($notif_id);
        if(!$check){
            return $notif_id;
        }else{
            $this->generateUUID();
        }
    }
    
    public function sendInAppNotif($data){
        $param = [
            'id' => $data['notif_id'],
            'user_id' => $data['target'],
            'message' => $data['message'],
            'type' => $data['type'],
            'title' => ucwords(strtolower($data['headings'])),
            'description' => $data['message'],
            'url' => $data['link'],
        ];
        Http::withOptions(['verify' => false,])
        ->withBody(json_encode($param),'application/json')
        ->get($this->lws.'/notification');
    }
    
    public function sendPushNotif($data){
        $is_push = false;
        $pids = $data['player_ids'];
        $temp_pids = [];
        $count_pids = 0;
        if(sizeof($pids)>0){
            for($i=0;$i<sizeof($pids);$i++){
                if($pids[$i] && !empty($pids[$i]) && $pids[$i]!=''){
                    $temp_pids[] = $pids[$i];
                }
                $count_pids++;
            }
        }
        if(sizeof($temp_pids)==$count_pids){
            $is_push = true;
        }else{
            if(sizeof($temp_pids)>0){
                $data['player_ids']=$temp_pids;
                $is_push = true;
            }
        }
        
        if($is_push){
            $push = new PushNotificationController();
            $push->sendPushNotif($data);
        }
    }
    
    public function sendMailNotif($data){
        if($data['mail_target_type']=="M"){
            Mail::to($data['order']->email)->send(new OrderMail($data['order']));
        }
        if($data['mail_target_type']=="H"){
            Mail::to($data['order']->email)->send(new OrderBookMail($data['order']));
        }
        if($data['mail_target_type']=="B"){
            Mail::to($data['order']->email)->send(new OrderBoatMail($data['order']));
        }
    }
    
    public function saveNotif($data){
        // $user,$toAdmin,$link,$type,$message,$channel;
        $notif_data = [
            'id' => $data['notif_id'],
            'for_admin' => $data['toAdmin'],
            'notification' => [
                'id' => $data['target'],
                'name' => $data['target_name'],
                // 'avatar' => 'https://pulo1000.com/images/avatar.png',
                'avatar' => $this->prod ?  'https://cdn.pulo1000.com/storage/user/avatar.png' : 'https://cdn.mykomodo.kabtour.com/storage/user/avatar.png',
                'link' => $data['link'],
                'type' => $data['type'],
                'title'=> ucwords(strtolower($data['headings'])),
                'message' => $data['message'],
                'target_role' => $data['target_role']
            ],
        ];
        return NotificationPush::create([
            'id' => $data['notif_id'],
            'type' => $data['channel'],
            'notifiable_type' => 'App\User',
            'notifiable_id' => $data['target'],
            'data' => json_encode($notif_data),
            'created_at' => date('Y-m-d H:i:s',strtotime('+7 Hours')),
            'updated_at' => date('Y-m-d H:i:s',strtotime('+7 Hours'))
        ]);
    }

    public function testSendMail($code){
        $checkBooking = Booking::where('code',$code)->first();
        if($checkBooking){
            $booking = Booking::find($checkBooking->id);
            if(substr($code,0,1)=='B'){
                Mail::to($booking->email)->send(new OrderBoatMail($booking));
            }
        }

    }

    public function testSendMailNew($code){
        $checkBooking = Booking::where('code',$code)->first();
        if($checkBooking){
            $booking = Booking::find($checkBooking->id);
            if(substr($code,0,1)=='B'){
                Mail::to($booking->email)->send(new OrderBoatMailNew($booking));
            }
        }

    }

    public function getNotifications($uid){
        $notif = NotificationPush::where('notifiable_id',$uid)->orderBY('created_at','desc')->get();
        return response()->json($notif);
    }
    
}
