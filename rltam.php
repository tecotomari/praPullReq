<?php
/**
 * Created by PhpStorm.
 * User: ace
 * Date: 2016/09/01
 * Time: 01:24
 * \
 */

require_once './vendor/autoload.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;





// 起動オプション確認、第一引数から設定ファイル名を取得する
// 設定ファイル：一行目：PDFパス。2行目：name,mailaddress
test();
main($argc, $argv);
exit;

/**
 * ライブラリなどのテスト
 */
function test() {
    // Sample: SwiftMailerの確認
    Swift_SmtpTransport::newInstance('TEST', 25);

}

/**
 * プログラムのメイン処理
 * @param $argc
 * @param $argv
 */
function main($argc, $argv) {

    $isViewHelp= false;
    $confFileName = getPhpOption( $argv );

    $log = getLog();
    $out = getOutput();

    if( empty($confFileName)) {
        // 設定ファイルが不正な場合
        $isViewHelp = true;
    }
    else {

        // 設定ファイルからリストデータを取得してくる
        $confData = readConfigFile($confFileName);

        // そのディレクトリが存在するか調べる
        $memberDirPath = $confData->getDirPath();
        if( isEnabledDir($memberDirPath) ) {
            
            var_dump($confData->getListMember());
            var_dump($confData->getArySkipData());
            var_dump($confData->getDirPath());
            

            // メンバーリスト分処理を行う
            foreach($confData->getListMember() as $member) {
                // リストから該当するディレクトリがあるかどうか
                if( empty( $member->getDirName() ) == false ) {

                    // ある
                    
                    // 送ってよいか処理の確認
                    // yを待つ
                    if( confirmMail($member) ) {
                        // 送信
                        sendMail($member);
                    } else {
                        // 中止
                    }
                } else {
                    // ない
                    $member->dispNoMember();
                }
            }

            // 実行結果の出力
            // 送った名前、メルアド、ファイルをログに出す
            dispResult();

        } else {
            //
            $isViewHelp = true;
        }
    }


    // ヘルプの出力が必要な場合
    if ($isViewHelp) {
        dispHelpThis();
    }
}


/**
 * Class ConfigData
 * readConfigFileで取り出したデータを各プロパティに格納するクラス
 * @author Tomari
 * @since PHP 5.6.24
 */
class ConfigData {
    private $dirPath; //個人ディレクトリが入ったディレクトリへのパス
    private $listMember; //Memberクラスのインスタンスを入れる
    private $arySkipData; //NOT(csv, tsv) かつ 行頭にスキップ

    function __construct() {
        $this->dirPath = __DIR__;
        $this->listMember = array();
        $this->arySkipData = array();
    }

    public function getDirPath() { return $this->dirPath; }
    public function getListMember() { return $this->listMember; }
    public function getArySkipData() { return $this->arySkipData; }
    public function setDirPath($dirPath) { $this->dirPath = $dirPath; }
    public function addListMember($member) { $this->listMember []= $member; }
    public function addArySkipData($data) { $this->arySkipData []= $data; }

}

/**
 * Class Member
 * readConfigFileで取り出した個人情報を各プロパティに格納するクラス
 * @author Tomari
 * @since PHP 5.6.24
 */
class Member {
    private $name; 
    private $mail;
    private $dirName; //名前でヒットした個人ディレクトリの「フルパス」
    private $aryFilePath; //個人ディレクトリ内のファイルパスの一覧
    
    function __construct() {
        $this->name = '';
        $this->mail = '';
        $this->dirName = '';
        $this->aryFilePath = array();
    }
    
    /**
     * 渡されたディレクトリパスの中に自分に当たるディレクトリがあれば設定する
     * @param string $path 個人ディレクトリが入ったディレクトリへのパス
     */
    public function setEnabledHitDir( $path ) {
        
        $aryDir = scandir( $path );
        
        //ディレクトリの一覧から名前が含まれているものを探す
        foreach( $aryDir as $dir ) {
            if( strpos( $dir, $this->name ) == true ) {
                //存在するならフルパスを渡す
                $this->dirName = realpath( $path ."/". $dir );
                
            }
        }
        
    }

    /**
     * 設定ファイルにはあってもディレクトリがなかった場合の注意文
     */
    static function dispNoMember() {

    }


    function setName($str) { $this->name = $str; }
    function setMail($str) { $this->mail = $str; }
    function setDirName($dirName) { $this->dirName = $dirName; }
    function getName() { return $this->name; }
    function getMail() { return $this->mail; }
    function getDirName() { return $this->dirName; }

    function addFilePath($path) { $this->aryFilePath = $path; }
}


/**
 *  PHP起動時のオプションを取得する
 *  起動引数が存在するか、またファイルが存在するかを確認する
 *  @author Tomari
 *  @param $argv array 起動時のオプション 第1引数 => ファイルパス(絶対パス もしくは 相対パス)
 *  @param $isRealPath bool 戻り値であるファイルパスを絶対パスにするフラグ デフォルトはfalse
 *  @return string ファイルパスを返す ファイルがない時は空で返す
 *  @throws Exception エラー発生時に呼び出し元の関数に例外を投げる
 */
function getPhpOption($argv, $isRealPath=false){
    $result = '';
    
    try {
        if( empty( $argv ) == false ){
            //引数があるとき
            array_shift( $argv );
            
            foreach( $argv as $str ){
                if( file_exists( $str ) == true ){
                    //該当するファイルが存在し、$isRealPathがtrueならば絶対パスを渡す
                    $result = ( $isRealPath == true ) ? realpath( $str ) : $str;
                }
                
                break;
            }
        }
        
    }catch ( Exception $e ){
        throw $e;
    }
    
    return $result;
}


/**
 * ファイルに出力するログ
 */
function getLog($level=Logger::DEBUG) {
    $log = new Logger('Log:');
    $handler = new StreamHandler('php://stdout', $level);
    $log->pushHandler($handler);

    return $log;
}


/**
 * 画面出力用のログ
 */
function getOutput($level=Logger::INFO) {
    $log = new Logger('Log:');
    $handler = new StreamHandler('php://stdout', $level);
    $log->pushHandler($handler);

    return $log;
}


/**
 * ファイルのパスから中身を読み込み、形式を確認してConfigDataのプロパティに分配する
 * 1行目は個人ファイルが入っているディレクトリへのパスのため分ける
 * ファイルの存在の有無の確認はここでは行わない
 * @author Tomari
 * @param string $path ファイルへのパス
 * @return object ConfigDataのインスタンス
 */
function readConfigFile( $path ) {
    //ConfigDataのインスタンスを作成する
    $result = new ConfigData();
    
    $aryFileText = file( $path, FILE_IGNORE_NEW_LINES );
    $result->setDirPath( array_shift( $aryFileText ) );

    //2行目から先のテキストが正しいフォーマットか確認する
    foreach( $aryFileText as $text ) {
        //csv, tsv形式かどうか
        if( checkFormatCsvTsv( $text ) == true ) {
            //csv,tsv形式のとき、行頭に特定の文字があるかどうか
            if( checkHeadStr( $text ) == true ) {
                //行頭に特定の文字がないとき、名前とメールアドレスに分ける
                $aryCutStr = array( ",", " " );
                
                foreach ( $aryCutStr as $cutStr ) {
                    if(strpos($text, $cutStr) ) {
                        $aryNameMail = explode( $cutStr , $text );
                        
                        $name = array_shift( $aryNameMail );
                        $mail = trim( array_shift( $aryNameMail ) );
                        
                        break;
                    }
                }
                
                //切り出したメールアドレスの形式が正しいか確認する
                if( checkFormatMail( $mail, $domain = '' ) == true ) {
                    //メールアドレスの形式が正しいときMemberのインスタンスを作成する。
                    $member = new Member();
                    
                    $member->setName( $name );
                    $member->setMail( $mail );
                    $member->setEnabledHitDir( $result->getDirPath() );
                    
                    if( file_exists( $member->getDirName() ) == true ) {
                        $member->addFilePath( array_diff( scandir($member->getDirName()), array('..', '.')));
                    }
                    //ConfigDataのプロパティにインスタンスを格納する
                    $result->addListMember( $member );
                    
                }else{
                    //メールの形式が間違っているとき
                    $result->addArySkipData( $text );
                }
                
            }else{
                //行頭に特定の文字があるとき
                $result->addArySkipData( $text );
            }
            
        }else{
            //csv,tsv形式じゃないとき
            $result->addArySkipData( $text );
        }
    }
    
    return $result;
}

/**
 * 名前リストからひとつづつ設定して返す
 * @author ace
 * @param $aryDataStr
 * @return array
 */
function setMemberList($aryDataStr) {
    $result = array();
    foreach($aryDataStr as $dataStr) {
        $member = new Member();
        if( checkFormatCsvTsv($dataStr, $member) ) {
            $result []= $member;
        }
    }

    return $result;
}


/**
 *  ファイルから抜き出したテキストが正しい形式か確認する
 * （ 名前,メールアドレス、名前, メールアドレス、名前	メールアドレス ）=> true
 *  @author Tomari
 *  @param string $text 確認するテキスト
 *  @return bool 正しければ true 間違っていれば false
 */
function checkFormatCsvTsv( $text ) {
    return ( preg_match("<[0-9a-zA-Zぁ-んァ-ヶ亜-熙]+(,|, |	).+@.+>", $text ) == 1 ) ? true : false;
}


/**
 *  行頭に特定の文字が入っているか確認
 *  確認する文字 => getPassHeadAry()
 *  @author Tomari
 *  @param string $text 確認するテキスト
 *  @param array $aryCheckWord 確認する文字 デフォルトはarray("\t",'/')
 *  @return bool あればfalse 無ければtrue
 */
function checkHeadStr( $text, $aryCheckWord=array("\t",'/') ) {   
    $result = true;
    
    foreach ( $aryCheckWord as $checkWord ) {
        if( strpos( $text, $checkWord ) === 0 ) {
            $result = false;
            break;
        }
    }
    
    return $result; 
}


/**
 *  行頭に付いていたらスキップする文字の配列を返す関数
 *  @author Tomari
 *  @return array 文字の配列
 */
function getPassHeadAry() {
    return array("\t",'/');
}
    

/**
 * 渡されたパスのディレクトリが存在するかどうか
 * @author ace, tomari
 * @param str $path ディレクトリへのパス
 * @return bool あればtrue 無ければfalse
 */
function isEnabledDir($path) {
    $result = false;
    
    if( file_exists($path) ) {
        
        $result = true;
    }
    
    return $result;
}


/**
 * メール送信の確認
 *
 */
function confirmMail($member) {

/*    名前、メルアド添付ファイル名をだして本当に送っていいか確認する
    userの入力をまってyの場合はおくる*/

    return false;
}


/**
 * メール送信
 *
 */
function sendMail($member) {
    return false;
}

/**
 * 実行結果の出力
 */
function dispResult() {
    // 誰にどのファイルを送ったか
}

/**
 * このプログラムの使い方を表示する
 */
function dispHelpThis() {
    /*
    使い方を出力する
     */
}


/**
 * 名前にあたるものが指定されたディレクトリにあるかどうか
 * @author ace, tomari
 * @param $dirPath 調べるディレクトリ
 * @param $name 調べる名前
 * @return string ヒットしたディレクトリ名
 */
function isEnabledHitDir($dirPath, $name) {
    $result = NULL;

    return $result;
}


/**
 *  メールアドレスが正しいか判別する関数
 *  第2引数にドメインを入れることで特定のドメインに対応できる
 *  @author Tomari
 *  @param  string  $mail  メールアドレス
 *  @param  string  $domain  ドメイン
 *  @return bool    $isResult 正しければ true , 間違っていると false
 */
function checkFormatMail( $mail, $domain = '' ){

    $isResult = false ;

    $mailMatch = '';

    //第2引数の有無で正規表現を切り替える
    if( empty( $domain ) ){

        //ドメイン指定なし
        $mailMatch = getMatchStrForMail();

    }else{

        //ドメイン指定があり
        $accountLen = strlen( $mail ) - strlen( $domain );

        $domainPos = strpos( $mail, $domain );

        //ドメインが特定の位置から始まっているとき
        if( $accountLen == $domainPos ){

            $mailMatch = getMatchStrForMail( $domain );

        }
    }

    //正規表現と一致するか調べる
    if( empty( $mailMatch ) == false ){

        $isResult = ( preg_match( $mailMatch, $mail ) == 1 ) ? true : false;
    }

    return $isResult;
}



/**
 *  ドメインの有無によってメールアドレス確認用の正規表現を変更する関数
 *  @author Tomari
 *  @param  string $domain  ドメイン
 *  @return string $result  メールアドレス確認用正規表現
 */
function getMatchStrForMail( $domain ='' ){

    $result = '';

    static $BASE = "^[a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+)*";

    if( empty( $domain ) ){

        //ドメイン指定なし
        $result = $BASE . "[@][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\- ]+)*$";

    }else{

        //ドメイン指定あり
        $result = $BASE . $domain;

    }

    return '<' . $result . '>';
}





















/*
---test.confファイル
/vagrant/www/k_work/testdir/
稲澤,inazawa@tecotec.co.jp
Tadahiko  tadahiko.tomari@tecotec.co.jp
Tomari,

/とまりただひこ	tadahiko.tomari@tecotec.co.jp
泊, tomari.tadahiko@tecotec.co.jp
	菅嶋,inazawa@tecotec.co.jp
---testdir内のディレクトリ
001 泊
002-稲澤
003 新井 管理部
R&D 菅嶋
---var_dump($confData->getListMember());結果
array(2) {
  [0]=>
  object(Member)#10 (4) {
    ["name":"Member":private]=>
    string(6) "稲澤"
    ["mail":"Member":private]=>
    string(21) "inazawa@tecotec.co.jp"
    ["dirName":"Member":private]=>
    string(38) "/vagrant/www/k_work/testdir/002-稲澤"
    ["aryFilePath":"Member":private]=>
    array(2) {
      [2]=>
      string(16) "inazawa_test.txt"
      [3]=>
      string(18) "inazawa_test_2.jpg"
    }
  }
  [1]=>
  object(Member)#9 (4) {
    ["name":"Member":private]=>
    string(3) "泊"
    ["mail":"Member":private]=>
    string(29) "tomari.tadahiko@tecotec.co.jp"
    ["dirName":"Member":private]=>
    string(35) "/vagrant/www/k_work/testdir/001_泊"
    ["aryFilePath":"Member":private]=>
    array(3) {
      [2]=>
      string(15) "tomari_test.txt"
      [3]=>
      string(17) "tomari_test_2.pdf"
      [4]=>
      string(17) "tomari_test_3.png"
    }
  }
}
---var_dump($confData->getArySkipData());結果
array(5) {
  [0]=>
  string(39) "Tadahiko  tadahiko.tomari@tecotec.co.jp"
  [1]=>
  string(7) "Tomari,"
  [2]=>
  string(0) ""
  [3]=>
  string(52) "/とまりただひこ   tadahiko.tomari@tecotec.co.jp"
  [4]=>
  string(29) "  菅嶋,inazawa@tecotec.co.jp"
  [5]=>
  string(41) "泊忠彦, tomari.tadahiko@teco@tec.co.jp"
}
---var_dump($confData->getDirPath());結果
string(28) "/vagrant/www/k_work/testdir/"
*/