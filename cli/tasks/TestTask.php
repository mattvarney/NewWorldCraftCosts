<?php

#use IMN\Html\Helper as HtmlHelper;
use IMNShare\Email\Handler as EmailHandler;

use IMNWork\Crawler\Helper as CrawlHelper;
use LinkwebV2\App\Library\StaticphqlHelper;
use LinkwebV2\App\Models\LmLink;
use LinkwebV2\App\Models\LmWbpEmployee;
use LinkwebV2\App\Models\CmEmailSentToHost;
use Curl\Curl;
use IMNShare\Html\Helper as HtmlHelper;
use IMNShare\Link\LinkHelper;
use Curl\MultiCurl;
use \IMNShare\Crawler\Request as Request;
use IMNShare\Uri\Parse as UrlParser;

class TestTask extends \Phalcon\CLI\Task
{

    public function testreplaceAction() {
        $text = "
            dph.georgia.gov
            https://dph.georgia.gov/covidtesting
            dph.georgia.gov
            dph.georgia.gov/covidtesting
            dph.georgia.gov
        ";
        $text = preg_replace('|https://dph\.georgia\.gov/covidtesting|', 'https://test.com/covid', $text);
        $text = preg_replace('|dph\.georgia\.gov|', 'test.com', $text);
        $text = preg_replace('|dph\.georgia\.gov/covidtesting|', 'test.com/covid', $text);

        echo $text;
    }

    public function getcontentTask() {
        $targetedContentMyisamObj = \CmTargetedContentMyisam::findFirstByTargetedContentId($targetedContentID);
        $articleResult = CrawlHelper::crawlNow($articleUrlParts['urlID']);
        $bestMatch = $this->getContentWithRegex($taskUrl, $targetedContentMyisamObj->content_body, $articleResult['content']);

    }
    private function getContentWithRegex($url, $articleHtml, $liveHtml) {
        $editedArticleHtml = HtmlHelper::stripAttrs(
            preg_replace('/<script.*?script>/is', '', $articleHtml),
            array('style', 'onclick', 'class')
        );
        $editedArticleHtml = $this->fixStupidCharacters($editedArticleHtml);
        $editedArticleHtml = str_replace("\xA0", ' ', html_entity_decode($editedArticleHtml) );

        if (stripos($editedArticleHtml, '<h') !== false) {
            $editedArticleHtml = strstr($editedArticleHtml, '<h');
        }
        $htmlHelper = new HtmlHelper($editedArticleHtml);
        $dom = $htmlHelper->getDom();

        $tagsToStrip = array('h1', 'h2');
        foreach ($tagsToStrip as $tag) {
            $tags = $dom->getElementsByTagName($tag);
            $remove = [];
            foreach ($tags as $item) {
                $remove[] = $item;
            }

            foreach ($remove as $item) {
                $item->parentNode->removeChild($item);
            }
        }

        $tagsToInvestigate = array('p');
        foreach ($tagsToInvestigate as $tag) {
            $tags = $dom->getElementsByTagName($tag);
            $remove = [];
            foreach ($tags as $item) {
                if(stripos($item->nodeValue, 'pixabay') !== false || stripos($item->nodeValue, 'upload.wikimedia') !== false){
                    $remove[] = $item;
                }
            }

            foreach ($remove as $item) {
                $item->parentNode->removeChild($item);
            }
        }

        $xpath = new \DOMXPath($dom);

        // Get the text of all of the tags left in the dom
        $nodes = $xpath->query('//text()');
        $textNodeContent = '';
        foreach ($nodes as $node) {
            $textNodeContent .= " " . $node->nodeValue;
        }
        $textNodeContent = trim($textNodeContent);

        $wordArr = explode(' ', $textNodeContent);
        foreach($wordArr as $key => $word) {
            $wordArr[$key] = trim($word, ".,()[]'\"-:?! \r\n\t");
            if(strlen($word)<= 2) {
                unset($word);
            }
        }
        $wordArr = array_filter($wordArr, 'trim');
//            print_r($wordArr);
        $firstFive = array_slice($wordArr, 0, 5);
        $lastFive = array_slice($wordArr, -5);

        foreach ($firstFive as $key => $value) {
            $firstFive[$key] = $value;
        }
        foreach ($lastFive as $key => $value) {
            $lastFive[$key] = $value;
        }

        $liveHtml = preg_replace('/<script.*?script>/is', '', $liveHtml);

        $htmlHelper = new HtmlHelper($liveHtml);
        $body = $htmlHelper->getTags('body', true);

        if(empty($body)) {
            echo "BODY EMPTY - $url \n\n";
            return;
        }
        $html = $body->getInnerHtml();
//            echo $html;

        $editedHtml = HtmlHelper::stripAttrs(
            $html,
            array('style', 'onclick', 'class')
        );
        $editedHtml = $this->fixStupidCharacters($editedHtml);

        $editedHtml = str_replace("\xA0", ' ', html_entity_decode($editedHtml) );
//            echo $editedHtml;

        for ($allowedDistance = 250; $allowedDistance <= 2000; $allowedDistance += 250) {
//                echo $allowedDistance . " ";
            $pattern = ">\s*(";
            foreach ($firstFive as $key => $word) {
                if ($key == count($firstFive) - 1) {
                    $pattern .= preg_quote($word, '@');
                } else {
                    $pattern .= preg_quote($word, '@') . ".{0," . $allowedDistance . "}";
                }
            }
            $pattern .= ".*?"; // separates the first words and the last words
            foreach ($lastFive as $key => $word) {
                if ($key == count($lastFive) - 1) {
                    $pattern .= preg_quote($word, '@');
                } else {
                    $pattern .= preg_quote($word, '@') . ".{0," . $allowedDistance . "}";
                }
            }
            $pattern .= ")[\s[:punct:]]*<"; // the end of the pattern
            $pattern = '@' . $pattern . '@';

            preg_match(
                $pattern . 'is',
                $editedHtml,
                $articleMatch
            );

            if (isset($articleMatch[0])) {
                return $articleMatch[0];
            }
        }
    }

    public function testscrapeAction() {
        $url = "https://www.kremp.com/blog/gardening/how-to-grow-apple-trees-at-home-by-kremp-florist/";
//        $url = "https://sucuri.net/index.php";
//        $url = "https://jimboykin.com";
//        $url = "https://www.wpbeginner.com/";
//        $url = "https://www.internetmarketingninjas.com/";
        $request = new Request(false, false, 3, false, 'chrome');
        $page = @$request->attempt($url, 'get', false, true); // last param is followRedirects
//        var_dump($page->client->redirectChain);
        error_log("Chrome got code " . $page->httpStatusCode . " for $url");

//        $request = new Request(false, false, 3, false, 'curl');
//        $page = @$request->attempt($url, 'get', false, false); // last param is followRedirects
//        error_log("Curl got code " . $page->httpStatusCode);
    }

    public function testarticlesAction() {
        $sql = '
            SELECT
                id,
                url
            FROM cm_targeted_content
            JOIN cm_targeted_content_myisam ON cm_targeted_content_myisam.targeted_content_id = cm_targeted_content.id
            WHERE url is not null
            AND cm_targeted_content.id = 17793
            AND length(cm_targeted_content_myisam.content_body) > 250
            ORDER BY id DESC';

        $records = $this->db->query(
            $sql,
            array()
        )->fetchAll();
        foreach ($records as $record) {
            $this->testArticle($record['url'], $record['id']);
        }
    }
    public function testArticle($url, $targetedContentID) {
        echo "$targetedContentID \n";
        $targetedContentMyisamObj = \CmTargetedContentMyisam::findFirstByTargetedContentId($targetedContentID);
        $request = new Request(false, false, 3, false, 'chrome');
        $page = @$request->attempt($url, 'get', false, true);

        if ($page->httpStatusCode  == 200) {
            $articleHtml = $targetedContentMyisamObj->content_body;
            $editedArticleHtml = HtmlHelper::stripAttrs(
                preg_replace('/<script.*?script>/is', '', $articleHtml),
                array('style', 'onclick', 'class')
            );
            $editedArticleHtml = $this->fixStupidCharacters($editedArticleHtml);
            $editedArticleHtml = str_replace("\xA0", ' ', html_entity_decode($editedArticleHtml) );

            if (stripos($editedArticleHtml, '<h') !== false) {
                $editedArticleHtml = strstr($editedArticleHtml, '<h');
            }
            $htmlHelper = new HtmlHelper($editedArticleHtml);
            $dom = $htmlHelper->getDom();

            $tagsToStrip = array('h1', 'h2');
            foreach ($tagsToStrip as $tag) {
                $tags = $dom->getElementsByTagName($tag);
                $remove = [];
                foreach ($tags as $item) {
                    $remove[] = $item;
                }

                foreach ($remove as $item) {
                    $item->parentNode->removeChild($item);
                }
            }

            $tagsToInvestigate = array('p');
            foreach ($tagsToInvestigate as $tag) {
                $tags = $dom->getElementsByTagName($tag);
                $remove = [];
                foreach ($tags as $item) {
                    if(stripos($item->nodeValue, 'pixabay') !== false || stripos($item->nodeValue, 'upload.wikimedia') !== false){
                        $remove[] = $item;
                    }
                }

                foreach ($remove as $item) {
                    $item->parentNode->removeChild($item);
                }
            }

            $xpath = new \DOMXPath($dom);

            // Get the text of all of the tags left in the dom
            $nodes = $xpath->query('//text()');
            $textNodeContent = '';
            foreach ($nodes as $node) {
                $textNodeContent .= " " . $node->nodeValue;
            }
            $textNodeContent = trim($textNodeContent);

            $wordArr = explode(' ', $textNodeContent);
            foreach($wordArr as $key => $word) {
                $wordArr[$key] = trim($word, ".,()[]'\"-:?! \r\n\t");
                if(strlen($word)<= 2) {
                    unset($word);
                }
            }
            $wordArr = array_filter($wordArr, 'trim');
//            print_r($wordArr);
            $firstFive = array_slice($wordArr, 0, 5);
            $lastFive = array_slice($wordArr, -5);

            foreach ($firstFive as $key => $value) {
                $firstFive[$key] = $value;
            }
            foreach ($lastFive as $key => $value) {
                $lastFive[$key] = $value;
            }

            print_r($page);
            $html = $page->response;
            echo $html;
            $html = preg_replace('/<script.*?script>/is', '', $html);

            $htmlHelper = new HtmlHelper($html);
            $body = $htmlHelper->getTags('body', true);

            if(empty($body)) {
                echo "BODY EMPTY - $url \n\n";
                return;
            }
            $html = $body->getInnerHtml();
//            echo $html;

            $editedHtml = HtmlHelper::stripAttrs(
                $html,
                array('style', 'onclick', 'class')
            );
            $editedHtml = $this->fixStupidCharacters($editedHtml);

            $editedHtml = str_replace("\xA0", ' ', html_entity_decode($editedHtml) );
//            echo $editedHtml;

            for ($allowedDistance = 250; $allowedDistance <= 2000; $allowedDistance += 250) {
//                echo $allowedDistance . " ";
                $pattern = ">\s*(";
                foreach ($firstFive as $key => $word) {
                    if ($key == count($firstFive) - 1) {
                        $pattern .= preg_quote($word, '@');
                    } else {
                        $pattern .= preg_quote($word, '@') . ".{0," . $allowedDistance . "}";
                    }
                }
                $pattern .= ".*?"; // separates the first words and the last words
                foreach ($lastFive as $key => $word) {
                    if ($key == count($lastFive) - 1) {
                        $pattern .= preg_quote($word, '@');
                    } else {
                        $pattern .= preg_quote($word, '@') . ".{0," . $allowedDistance . "}";
                    }
                }
                $pattern .= ")[\s[:punct:]]*<"; // the end of the pattern
                $pattern = '@' . $pattern . '@';

                preg_match(
                    $pattern . 'is',
                    $editedHtml,
                    $articleMatch
                );

                if (isset($articleMatch[0])) {
                    break;
                }
            }

            $links = array();

            if (isset($articleMatch[0])) {
                preg_match_all(
                    '/<a\s.*?href=[\'\"](.*?)[\'\"].*?(rel=[\'\"](.*?)[\'\"].*?)?>/im',
                    $articleMatch[0],
                    $hrefMatches
                );

                if (count($hrefMatches[1])) {
                    $count = count($hrefMatches[0]);
                    for ($i = 0; $i < $count; $i++) {
                        array_push(
                            $links,
                            array(
                                'element' => $hrefMatches[0][$i],
                                'href' => $hrefMatches[1][$i],
                                'rel' => $hrefMatches[3][$i],
                            )
                        );
                    }
                }
                $urlParser = new UrlParser();
                $parsed = @$urlParser->parse($url);

                $rootDomain = $parsed['rootDomain'];
                $linkDestinations = [
                    'internal' => [],
                    'external' => [],
                ];
                foreach($links as $key => $link) {
                    if(stripos($link['href'], 'http') !== 0) {
                        $linkDestinations['internal'][] = $link;
                    } else if(stripos($link['href'], $rootDomain) !== false) {
                        $linkDestinations['internal'][] = $link;
                    } else {
                        $linkDestinations['external'][] = $link;
                    }
                }
                echo "Internal: " . count($linkDestinations['internal']) ."\n";
                echo "External: " . count($linkDestinations['external'])  ."\n";
            } else {
                echo "$url \n";
                echo $pattern . "\n";
            }
        } else {
            echo $page->httpStatusCode . " $targetedContentID $url \n";
        }
//        echo "=================\n\n";
    }

    public function fixStupidCharacters(string $str) :string {
        $chr_map = array(
            // Windows codepage 1252
            "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
            "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
            "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
            "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
            "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
            "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
            "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
            "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark

            // Regular Unicode     // U+0022 quotation mark (")
            // U+0027 apostrophe     (')
            "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
            "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
            "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
            "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
            "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
            "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
            "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
            "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
            "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
            "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
            "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
            "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
            "\xE2\x80\x8B" => "", // zero width space
        );
        $chr = array_keys  ($chr_map); // but: for efficiency you should
        $rpl = array_values($chr_map); // pre-calculate these two arrays
        $str = str_replace($chr, $rpl, html_entity_decode($str, ENT_QUOTES, "UTF-8"));
        return $str;
    }
    public function mainAction()
    {
        $mailStatus = EmailHandler::sendMail(
            "matt@imninjas.com",
            'New Action Item',
            EmailHandler::renderTemplate(
                'IMN/new-action-item',
                array(
                    'clientSiteUrl' => 'http://www.imninjas.com'
                )
            )
        );

    }

    public function poopAction()
    {
        $multi_curl = new MultiCurl();


        $multi_curl->setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8');
        $multi_curl->setHeader('Accept-Language', 'en-US,en;q=0.9');
        $multi_curl->setHeader('Cache-Control', 'max-age=0');
        $multi_curl->setHeader('Upgrade-Insecure-Requests', 1);
        $multi_curl->setHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.108 Safari/537.36');

        $multi_curl->success(function ($instance) {
            echo $instance->response;
        });
//        $multi_curl->addGet('http://hipiers.com/17oct.html');




        $multi_curl->addGet('http://testing-dev004.wbpnet.pvt/linkwebv2/headers.php');
        //$multi_curl->addGet('https://www.internetmarketingninjas.com');
        $multi_curl->start();
    }

    public function crapAction()
    {
        $curl = new Curl();


        $curl->get('http://testing-dev004.wbpnet.pvt/linkwebv2/headers.php');
//        $curl->get('http://hipiers.com/17oct.html');
        //$curl->get('https://www.internetmarketingninjas.com');
        echo $curl->response;
    }
}
