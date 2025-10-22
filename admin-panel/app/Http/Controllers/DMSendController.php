<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageLog;
use App\Models\Seller;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Tags\Tag;


class DMSendController extends Controller
{

    public function __invoke(): void
    {

        $start = Carbon::now();
        $sendMessages = Message::where('is_completed', 'N')->where(function ($q) use ($start) {
            $q->whereNull('start_at');
            $q->orWhere('start_at', '<=', $start);
        })->get();

        // Check if there are any messages to send
        if ($sendMessages->isNotEmpty()) {
            foreach ($sendMessages as $message) {
                $message->update(['is_completed' => 'S', 'updated_at' => Carbon::now()]);
                $logs = MessageLog::where('message_id', $message->id)->with('seller')->get();
                $sellerData = $logs->map(function ($log) {
                    return [
                        'username' => $log->seller->instagram_name,
                    ];
                })->toArray();
                $body_data = [
                    'account_username' => $message->sender->login_id,
                    'account_password' => $message->sender->login_password,
                    'usernames' => $sellerData,
                    'message' => json_decode($message->message_json, true),
                ];

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'ngrok-skip-browser-warning' => '69420',
                ])->timeout(60)->post('https://broadly-novel-pangolin.ngrok-free.app/run-instagram-bot', $body_data);
                $end = Carbon::now();

                if ($response->successful()) {
                    $message->update(['is_completed' => 'Y', 'end_at' => $end, 'updated_at' => $end]);
//                    $data = $response->body();
//
//                    foreach ($data['data']['result'] as $username => $item) {
//                        $seller = Seller::where('instagram_name', $username)->first();
//                        $sellerId = $seller->id;
//                        MessageLog::where('message_id', $message->id)->where('seller_id', $sellerId)->update(['result' => $item == 'success' ? 'Y' : 'N' , 'result_text' => $item['result']]);
//                    }
                } else {
                    $message->update(['is_completed' => 'E']);
                    \Log::error('Failed to send message: ' . $response->body());
                }
            }
        }

    }
}
