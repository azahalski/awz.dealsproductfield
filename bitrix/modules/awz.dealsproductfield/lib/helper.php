<?php
namespace Awz\GitMd;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Application;

class Helper {

    public static $forseUpdate = false;
    public static $mermaid = false;

    /**
     * @param string $content
     * @param int $expiredTime
     * @return null
     */
    public static function replaceGitHubLink(string &$content, int $expiredTime=86400)
    {
        if(preg_match_all('/([A-z0-9\-]+)?\:?(https\:\/\/.*\.md)/Uis', $content, $matches)){
            if(isset($matches[2])){
                foreach($matches[2] as $key=>$url){
                    $urlContent = self::getHtml($url, $expiredTime);
                    $urlContentRow = '';
                    $keyPage = '';
                    if(isset($matches[1][$key]) && $matches[1][$key]){
                        $keyPage = $matches[1][$key].':';
                        if(strpos($urlContent,$matches[1][$key].'-start')!==false &&
                            strpos($urlContent,$matches[1][$key].'-end')!==false
                        ){
                            $pageNum = $matches[1][$key];
                            $urlContentRow = preg_replace(
                                '/(.*?)(<!-- '.$pageNum.'-start -->.*<!-- '.$pageNum.'-end -->)(.*?)/Uis',
                                "$2",
                                $urlContent
                            );
                        }else{
                            $urlContentRow = '';
                        }
                        //$content = str_replace($keyPage, '', $content);
                    }
                    //echo'<pre>';print_r($urlContentRow);echo'</pre>';
                    $content = str_replace($keyPage.$url, $urlContentRow, $content);
                }
            }
        }

        if(preg_match_all('/<img src="([^"]+)"[^>]+?>/Uis',$content, $matches)){
            foreach($matches[1] as $key=>$image){
                $imageSrc = str_replace(array('https://zahalski.dev'),'',$image);
                $img = str_replace(array('https://zahalski.dev'),'',$matches[0][$key]);
                $content = str_replace($matches[0][$key], '<a class="fancybox" href="'.$imageSrc.'">'.$img.'</a>', $content);
            }
            //echo'<pre>';print_r($matches);echo'</pre>';
        }

        return null;
    }

    /**
     * @param string $url
     * @param int $expiredTime
     * @return string
     */
    public static function getHtml(string $url, int $expiredTime=86400):string
    {
        $parseDown = new Parsedown();
        $content = self::getContent($url, $expiredTime);
        $content = $content ? $parseDown->text($content) : "";
        if($content){
            $content = str_replace(
                '<pre><code class="language-mermaid">',
                '<pre><pre class="mermaid">',
                $content
            );
            if(mb_strpos($content, 'class="mermaid"')!==false){
                self::$mermaid = true;
            }
        }
        return $content;
    }

    /**
     * @param string $url
     * @param int $expiredTime
     * @return string
     */
    public static function getContent(string $url, int $expiredTime=86400): string
    {
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        if($request->get('clear_cache')=='Y'){
            self::$forseUpdate = true;
        }

        $hash = md5($url);

        $currentDateOb = Datetime::createFromTimestamp(time());
        $expiredDateOb = Datetime::createFromTimestamp(time()+$expiredTime);

        $ob = ContentTable::getList(array(
            'select'=>array('*'),
            'filter'=>array(
                '=HASH'=>$hash
            ),
            'limit'=>1,
            'order'=>array('ID'=>'DESC')
        ))->fetchObject();

        $updateContent = false;

        $content = '';
        if($ob && $ob->get('CONTENT')){
            $content = $ob->get('CONTENT');
        }

        if(!$ob || ($ob->get('EXPIRED_DATE') < $currentDateOb) || self::$forseUpdate){
            $newContent = self::getExtContent($url);
            if($newContent){
                $content = $newContent;
            }
            $updateContent = true;
        }

        $updatedFields = array(
            'LINK'=>$url,
            'HASH'=>$hash,
            'EXPIRED_DATE'=>$expiredDateOb,
        );
        if($content){
            $updatedFields['CONTENT'] = $content;
        }
        if($updateContent){
            if($ob && $ID=$ob->get('ID')){
                ContentTable::update(array('ID'=>$ID), $updatedFields);
            }else{
                if(!$updatedFields['CONTENT']){
                    $updatedFields['CONTENT'] == $url;
                }
                $updatedFields['CREATE_DATE'] = $currentDateOb;
                ContentTable::add($updatedFields);
            }
        }

        if($updatedFields['CONTENT']){
            return $updatedFields['CONTENT'];
        }

        return "";

    }

    /**
     * @param string $url
     * @return string
     */
    public static function getExtContent(string $url): string
    {
        if(strpos($url, 'github.com')) {
            $url = str_replace('github.com', 'raw.githubusercontent.com', $url);
            $url = str_replace('/blob', '', $url);
        }

        $httpClient = new HttpClient(array(
            'socketTimeout'=>5,
            'streamTimeout'=>5,
        ));
        $httpClient->disableSslVerification();
        $content = $httpClient->get($url);

        if($content == '404: Not Found') $content = '';

        return $content ?: "<a href=\"".$url."\">".$url."</a>";
    }

}