<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageLog;
use App\Models\Seller;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BulkSellerRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sreg:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sellerArray = [
            ['eun_jeong8'	,'은조미',],
            ['ys.ttol'	,'예소리',],
            ['yoon__bling'	,'윤블링',],
            ['gyuuujin'	,'규진',],
            ['dew0912'	,'듀앳',],
            ['apricotyeon'	,'정순주',],
            ['nana6a6y'	,'나나',],
            ['jung_kyukyu'	,'규규',],
            ['eungelll'	,'고은',],
            ['2905_home'	,'선선부부',],
            ['marie_yeon'	,'마썰틴',],
            ['ryu_angel'	,'류해성',],
            ['ehee45'	,'최은희',],
            ['wjdsbom'	,'수현',],
            ['sol._.ll'	,'진솔',],
            ['cho.jiii'	,'러브조지',],
            ['_.zoo._'	,'쥬드',],
            ['misszzie_'	,'미쓰찌언니',],
            ['sarah_x_life'	,'우유커플',],
            ['j.on___'	,'은정',],
            ['endorphin.oh'	,'엔도르',],
            ['callmeyeal'	,'예린',],
            ['lavie_rim'	,'최혜림',],
            ['soulssa'	,'솔싸',],
            ['banhaejji_'	,'김혜지',],
            ['yulri_0i'	,'율리',],
            ['haumjie'	,'하엄지',],
            ['jessicak_smile'	,'강명은',],
            ['waterparang'	,'신주희',],
            ['choiheeann'	,'최희',],
            ['jaeheeng'	,'심재희',],
            ['hansomang'	,'더위시',],
            ['love_boki'	,'러브미우',],
            ['_kimgre'	,'김그레',],
            ['jex_xy'	,'젝시',],
            ['36.5c_'	,'민정',],
            ['laun_kr'	,'이윤이',],
            ['vividyooni_'	,'송지윤',],
            ['gkruddll'	,'하경',],
            ['m___sz'	,'이이재',],
            ['pilates_yj'	,'윤진쌤',],
            ['thing_1022'	,'김소영',],
            ['parkahin'	,'박아인',],
            ['choi__yoojin'	,'최유진',],
            ['jjeuneu'	,'박지은',],
            ['nohohon_'	,'예은',],
            ['rockchaeeun'	,'락채은',],
            ['nnino___'	,'위드니노',],
            ['bboseong_lee'	,'이보성',],
            ['ih_dressroom'	,'인드레',],
            ['ka_young2000'	,'가영',],
            ['8_jjini'	,'양진',],
            ['hinzajoa'	,'혜미',],
            ['thanks_kim'	,'김현아',],
            ['hairstyle_jihye'	,'지혜',],
            ['mimiwor'	,'김민희',],
            ['luv_banie'	,'정현경',],
            ['hwawon__'	,'화원',],
            ['by.jiae'	,'바이지애',],
            ['9.13mimi'	,'미미',],
            ['___bomi___'	,'보미',],
            ['areumsongee'	,'아름송이',],
            ['xxjominxx'	,'조민영',],
            ['jiyoon_park_'	,'박지윤',],
            ['seobori_'	,'보리',],
            ['__eun.me__'	,'설은미',],
            ['_shopbong'	,'봉선아',],
            ['ttovely__'	,'김은희',],
            ['double_joo'	,'이효주',],
            ['0cean_boutique'	,'오션',],
            ['mimi_wld'	,'미미',],
            ['zibbeuni'	,'지은',],
            ['nangvely'	,'낭블리',],
            ['gaon_mama_'	,'가온시온맘',],
            ['minigyul__'	,'미니결',],
            ['jxxmunkyung'	,'정문경',],
            ['_mssssssss'	,'김민선',],
            ['find_h'	,'규리',],
            ['hjxx1_'	,'모브',],
            ['oxxooi'	,'윤주',],
            ['lloveeely'	,'진예영',],
            ['strawberryjam_m'	,'사재미',],
            ['sasim_hj'	,'사심희',],
            ['banchae0'	,'반채영',],
            ['__pairi'	,'파이리',],
            ['jjaeris'	,'최유리',],
            ['ahxmin'	,'에그민',],
            ['ttvate'	,'영해',],
            ['bible_seo'	,'서성경',],
            ['j._.aen'	,'제니앤',],
            ['shuniiyou'	,'슈니',],
            ['__misulin'	,'미슐린',],
        ];
        // 메세지를 보내야 할 리스트를 가져온다.
        $insertSql = [];
        $tagData = ['신규 A'];
        foreach ($sellerArray as $seller) {
            $existSeller = Seller::where('instagram_name', $seller[0])->count();
            if ($existSeller > 0) {
                echo $seller[0] . " is exist \n";
                continue;
            }
            

//            // 셀러 데이터 입력
//            $newSeller = Seller::create([
//                'instagram_name'      => $seller[0],
//                'name'                => $seller[1],
//            ]);
//            // 셀러 태그 생성
//            if (! empty($tagData)) {
//                $tags = is_string($tagData) ? explode(',', $tagData) : $tagData;
//                // 배열의 각 태그에 trim()을 적용하여 앞뒤 공백 제거
//                $tags = array_map('trim', $tags);
//                $newSeller->attachTags($tags, 'seller');
//            }
        }

    }
}
