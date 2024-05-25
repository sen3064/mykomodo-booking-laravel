<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OneSignal;

class PushNotificationController extends Controller
{
    //
    public $cfg = [
        //prod key
        // 'ONESIGNAL_MITRA_APP_ID'=>'09a5f19b-fa10-4d82-b7a4-87be9cf1acd5',
        // 'ONESIGNAL_MITRA_REST_API_KEY'=>'NjMwZmYxODgtYTdhNi00MzI0LWExMWItMTJmZDg1ZWRiYTcx',
        
        // 'ONESIGNAL_WISATAWAN_APP_ID'=>'e9fb8fcb-2017-4d2c-9855-2eef2334b191',
        // 'ONESIGNAL_WISATAWAN_REST_API_KEY'=>'NjVlYmE5MGQtMzJkYi00YzYyLWJjNDctOTA1NjY4MjA5MTE4',

        //dev key
        'ONESIGNAL_MITRA_APP_ID'=>'bbd809f7-df41-438d-b0d5-5da554db22ec',
        'ONESIGNAL_MITRA_REST_API_KEY'=>'YWMzNTc2YzEtYmQwMy00OTVhLWJjY2UtMWMyZmE0MDk1ZDVm',
        
        'ONESIGNAL_WISATAWAN_APP_ID'=>'e014b47a-98a6-4c4d-a524-bb2d44cab099',
        'ONESIGNAL_WISATAWAN_REST_API_KEY'=>'NzFjZDUzOWUtMWYwYi00ZWJhLTkxMTQtNDVjOTE0OWUxYWJj',
        
        // 'ONESIGNAL_POKDARWIS_APP_ID'=>'3280aef7-d1ac-47ec-a34e-4e27952dc53e',
        // 'ONESIGNAL_POKDARWIS_REST_API_KEY'=>'Yzg1ZTBmZWUtNGJjYy00OTc5LWIxNzYtNmNlMjYxMTQ3YWI5',
        
        // 'ONESIGNAL_BOAT_OWNER_APP_ID'=>'88638c0a-49ce-43e5-8598-7929fc8ae7f3',
        // 'ONESIGNAL_BOAT_OWNER_REST_API_KEY'=>'NmMwN2FhYmEtYzA3OC00YWQzLTg1YjMtMzRiYTUwOTQ4ODAy',
        
        // 'ONESIGNAL_MITRA_KAPAL_APP_ID'=>'5cc1952d-c4ab-4c80-8d21-9826f12251b2',
        // 'ONESIGNAL_MITRA_KAPAL_REST_API_KEY'=>'ZDU4NjBlMDgtNjVhYy00MmI0LWEyMDUtNGVjMTcxMmQ4YmU4',
    ];
    
    public function sendPushNotif($data){
        $data['app_id'] = $this->cfg['ONESIGNAL_'.strtoupper($data['target_role']).'_APP_ID'];
        $data['api_key'] = $this->cfg['ONESIGNAL_'.strtoupper($data['target_role']).'_REST_API_KEY'];
        $contents = array(
            "en" => $data['message']
        );
     
        $params = array(
            'app_id' => $data['app_id'],
            'contents' => $contents,
            'api_key' => $data['api_key'],
            'include_player_ids' => $data['player_ids'],
            // 'small_icon' => 'ic_launcher'
            'small_icon' => 'launcher_icon'
        );
     
        // if (isset($data)) {
        //     $params['data'] = $data;
        // }
     
        if(isset($data['headings'])){
            $params['headings'] = array(
                "en" => $data['headings']
            );
        }
        if(isset($data['link']) && $data['link']!=''){
            // $params['url'] = $data['link'];
            $params['data']['url'] = $data['link'];
        }
        if(isset($data['type']) && $data['type']!=''){
            $params['data']['type'] = $data['type'];
        }
        // dd($params);
        OneSignal::sendNotificationCustom($params);
    }
}
