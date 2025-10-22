<?php

use App\Http\Controllers\DMSendController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Schedule::command('dmsend:cron')->everyTwoMinutes();

// 자동 DM 발송 - 매분마다 확인 (각 설정의 스케줄에 따라 실행)
Schedule::command('tiktok:send-auto-dm')->everyMinute();

//Schedule::command('tiktok:send-scheduled-messages')->everyMinute();
//
//// TikTok 비디오 스크랩 - 매일 오전 2시 실행
//Schedule::command('tiktok:daily-scrape')->dailyAt('02:00');
//
//// TikTok 업로드 체크 - 매일 오전 4시 실행 (스크랩 2시간 후)
//Schedule::command('tiktok:daily-scrape --check-only')->dailyAt('04:00');

// Schedule::call(new DMSendController)->everyFiveMinutes();
// 인스타그램 인플루언서들에 대한 정보를 가져오는 스케쥴링에 대한 개발이 필요하다.
// 인플루언서 게시물숫자 팔로워 숫자, 팔로잉 회원정보
