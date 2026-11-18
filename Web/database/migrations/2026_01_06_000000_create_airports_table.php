<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 【設計書からの拡張】機体マップ表示用の空港座標マスタ。
 *
 * 確度に関する注記: 座標は日本国内の主要空港と世界の主要ハブ空港、計約100件を
 * 一般に公開されている空港情報をもとに登録した近似値。滑走路中心などの厳密な測量値ではなく
 * 「地図上でおおよそその空港とわかる」精度を狙ったもの。登録が無い空港・座標がずれている空港は
 * マップ画面から誰でも追加・補正できるようにしてある（source='manual'として区別）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->char('icao', 4)->primary();
            $table->string('name', 100);
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->enum('source', ['seed', 'manual'])->default('seed');
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();
        $seed = function (string $icao, string $name, float $lat, float $lon) use ($now) {
            return [
                'icao' => $icao, 'name' => $name, 'lat' => $lat, 'lon' => $lon,
                'source' => 'seed', 'created_at' => $now,
            ];
        };

        $rows = [
            // --- 日本国内の主要空港 ---
            $seed('RJTT', '東京国際空港（羽田）', 35.5494, 139.7798),
            $seed('RJAA', '成田国際空港', 35.7647, 140.3864),
            $seed('RJBB', '関西国際空港', 34.4347, 135.2441),
            $seed('RJOO', '大阪国際空港（伊丹）', 34.7855, 135.4382),
            $seed('RJGG', '中部国際空港（セントレア）', 34.8584, 136.8054),
            $seed('RJCC', '新千歳空港', 42.7752, 141.6923),
            $seed('RJCH', '函館空港', 41.7700, 140.8219),
            $seed('RJFF', '福岡空港', 33.5859, 130.4508),
            $seed('RJOA', '広島空港', 34.4361, 132.9194),
            $seed('RJOT', '高松空港', 34.2142, 134.0157),
            $seed('RJOM', '松山空港', 33.8272, 132.6997),
            $seed('RJOK', '高知龍馬空港', 33.5461, 133.6692),
            $seed('RJOC', '出雲空港', 35.4136, 132.8899),
            $seed('RJNK', '小松空港', 36.3947, 136.4064),
            $seed('RJSS', '仙台空港', 38.1397, 140.9169),
            $seed('RJSK', '秋田空港', 39.6156, 140.2186),
            $seed('RJSN', '新潟空港', 37.9558, 139.1211),
            $seed('RJSA', '青森空港', 40.7347, 140.6908),
            $seed('RJSM', '三沢空港', 40.7031, 141.3686),
            $seed('RJSY', '山形空港', 38.4117, 140.3708),
            $seed('RJCB', 'とかち帯広空港', 42.7333, 143.2172),
            $seed('RJEC', '女満別空港', 43.8806, 144.1642),
            $seed('RJCK', '釧路空港', 42.9750, 144.1928),
            $seed('RJCW', '稚内空港', 45.4042, 141.8006),
            $seed('RJCJ', '旭川空港', 43.6708, 142.4475),
            $seed('RJCM', '紋別空港', 44.3053, 143.4053),
            $seed('ROAH', '那覇空港', 26.1958, 127.6458),
            $seed('RORS', '石垣空港', 24.3964, 124.2450),
            $seed('RORT', '宮古空港', 24.7828, 125.2953),
            $seed('RORK', '久米島空港', 26.3636, 126.7139),
            $seed('RJOB', '岡山空港', 34.7569, 133.8553),
            $seed('RJOR', '鳥取空港', 35.5300, 134.1667),
            $seed('RJOY', '米子空港（美保）', 35.4922, 133.2367),
            $seed('RJDT', '徳島空港', 34.1328, 134.6067),
            $seed('RJDB', '屋久島空港', 30.3856, 130.6592),
            $seed('RJDC', '福江空港（五島）', 32.6669, 128.8347),
            $seed('RJKA', '鹿児島空港', 31.8034, 130.7194),
            $seed('RJKB', '阿蘇くまもと空港', 32.8373, 130.8550),
            $seed('RJOI', '壱岐空港', 33.7492, 129.7856),
            $seed('RJDU', '対馬空港', 34.2836, 129.3306),
            $seed('RJKN', '喜界空港', 28.3200, 129.9231),
            $seed('RJNS', '富士山静岡空港', 34.7961, 138.1897),
            $seed('RJTO', '八丈島空港', 33.1150, 139.7856),
            $seed('RJAH', '茨城空港（百里）', 36.1811, 140.4147),
            $seed('RJOH', '八尾空港', 34.5967, 135.6017),
            $seed('RJFN', '種子島空港', 30.6053, 130.9908),
            $seed('RJFO', '大分空港', 33.4794, 131.7369),
            $seed('RJFU', '長崎空港', 32.9169, 129.9136),
            $seed('RJFR', '宮崎空港', 31.8772, 131.4486),
            $seed('RJFT', '北九州空港', 33.8459, 131.0350),

            // --- 世界の主要ハブ空港 ---
            $seed('KJFK', 'John F. Kennedy Intl (New York)', 40.6413, -73.7781),
            $seed('KLAX', 'Los Angeles Intl', 33.9416, -118.4085),
            $seed('KORD', "Chicago O'Hare Intl", 41.9742, -87.9073),
            $seed('KSFO', 'San Francisco Intl', 37.6213, -122.3790),
            $seed('KATL', 'Hartsfield-Jackson Atlanta Intl', 33.6407, -84.4277),
            $seed('KDFW', 'Dallas/Fort Worth Intl', 32.8998, -97.0403),
            $seed('KSEA', 'Seattle-Tacoma Intl', 47.4502, -122.3088),
            $seed('KDEN', 'Denver Intl', 39.8561, -104.6737),
            $seed('KMIA', 'Miami Intl', 25.7959, -80.2870),
            $seed('KBOS', 'Boston Logan Intl', 42.3656, -71.0096),
            $seed('EGLL', 'London Heathrow', 51.4700, -0.4543),
            $seed('EGKK', 'London Gatwick', 51.1481, -0.1903),
            $seed('LFPG', 'Paris Charles de Gaulle', 49.0097, 2.5479),
            $seed('EDDF', 'Frankfurt am Main', 50.0379, 8.5622),
            $seed('EDDM', 'Munich', 48.3538, 11.7861),
            $seed('EHAM', 'Amsterdam Schiphol', 52.3105, 4.7683),
            $seed('LEMD', 'Madrid Barajas', 40.4983, -3.5676),
            $seed('LEBL', 'Barcelona El Prat', 41.2971, 2.0785),
            $seed('LIRF', 'Rome Fiumicino', 41.8003, 12.2389),
            $seed('LSZH', 'Zurich', 47.4647, 8.5492),
            $seed('LOWW', 'Vienna Intl', 48.1103, 16.5697),
            $seed('EKCH', 'Copenhagen', 55.6180, 12.6560),
            $seed('ESSA', 'Stockholm Arlanda', 59.6519, 17.9186),
            $seed('ENGM', 'Oslo Gardermoen', 60.1976, 11.1004),
            $seed('EFHK', 'Helsinki-Vantaa', 60.3172, 24.9633),
            $seed('UUEE', 'Moscow Sheremetyevo', 55.9726, 37.4146),
            $seed('VHHH', 'Hong Kong Intl', 22.3080, 113.9185),
            $seed('ZBAA', 'Beijing Capital Intl', 40.0799, 116.6031),
            $seed('ZSPD', 'Shanghai Pudong Intl', 31.1443, 121.8083),
            $seed('ZGGG', 'Guangzhou Baiyun Intl', 23.3924, 113.2988),
            $seed('RKSI', 'Seoul Incheon Intl', 37.4602, 126.4407),
            $seed('RCTP', 'Taipei Taoyuan Intl', 25.0797, 121.2342),
            $seed('WSSS', 'Singapore Changi', 1.3644, 103.9915),
            $seed('WMKK', 'Kuala Lumpur Intl', 2.7456, 101.7099),
            $seed('VTBS', 'Bangkok Suvarnabhumi', 13.6900, 100.7501),
            $seed('WIII', 'Jakarta Soekarno-Hatta', -6.1256, 106.6559),
            $seed('RPLL', 'Manila Ninoy Aquino Intl', 14.5086, 121.0198),
            $seed('VABB', 'Mumbai Chhatrapati Shivaji', 19.0887, 72.8679),
            $seed('VIDP', 'Delhi Indira Gandhi Intl', 28.5562, 77.1000),
            $seed('OMDB', 'Dubai Intl', 25.2532, 55.3657),
            $seed('OTHH', 'Doha Hamad Intl', 25.2731, 51.6081),
            $seed('OERK', 'Riyadh King Khalid Intl', 24.9576, 46.6988),
            $seed('FAOR', 'Johannesburg OR Tambo', -26.1392, 28.2460),
            $seed('HECA', 'Cairo Intl', 30.1219, 31.4056),
            $seed('YSSY', 'Sydney Kingsford Smith', -33.9399, 151.1753),
            $seed('YMML', 'Melbourne Tullamarine', -37.6690, 144.8410),
            $seed('NZAA', 'Auckland Intl', -37.0082, 174.7850),
            $seed('CYYZ', 'Toronto Pearson Intl', 43.6777, -79.6248),
            $seed('CYVR', 'Vancouver Intl', 49.1939, -123.1844),
            $seed('SBGR', 'Sao Paulo Guarulhos Intl', -23.4356, -46.4731),
        ];

        // insertOrIgnore: 主キー重複時にエラーで落とさない（将来的にこの一覧を編集・再実行しても安全なように）。
        DB::table('airports')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
