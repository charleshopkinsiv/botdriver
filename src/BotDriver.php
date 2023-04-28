<?php

namespace BotDriver;

use App\Models\ClAccount;
use Facebook\WebDriver\Chrome\ChromeDriver;
use WebDriverLoader\ChromeDriverLoader;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\{RemoteWebElement,RemoteWebDriver};
use App\Models\Account;


class BotDriver
{

    
    // private static string   $select_items_selectors = "span";
    private static string $select_items_selectors   = "ul.selection-list > li";
    private static string $select_radio_selectors   = "label.radio-option";
    public  static string $screenshot_dir           = __DIR__ . "/../../storage/app/public/screenshots/";
    private static string $cookies_dir              = __DIR__ . "/../data/cookies/";

    private ClAccount       $account;
    private RemoteWebDriver $WebDriver;
    private string          $user_agent =   'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                                            'AppleWebKit/537.36 (KHTML, like Gecko) ' . 
                                            'Chrome/108.0.0.0 Safari/537.36';
    private string          $ip_address = '';
    private array           $cookies = [];
    public static bool      $debug      = true;


    public function __construct()
    {

    }


    public function __destruct()
    {

        if(!empty($this->WebDriver)) {
            
            $this->WebDriver->close();
            $this->WebDriver->quit();
            unset($this->WebDriver);
        }
    }


    public function setAccount(ClAccount $account)    { $this->account = $account; }
    public function getAccount()                    { return $this->account; }


    public function setIpAddress(string $ip_address) { $this->ip_address = $ip_address; }
    

    public function setUserAgent(string $user_agent) { $this->user_agent = $user_agent; }


    public function load(string $url)
    {

        $this->webDriver()->get($url);
    }


    public function getCookieFileLoc(string $account) : string
    {

        return self::$cookies_dir . md5($account);
    }


    public function getCookies(string $account)
    {

        if(empty($this->cookies)
        && is_file($this->getCookieFileLoc($account))) {

            $this->cookies = unserialize(
                file_get_contents($this->getCookieFileLoc($account))
            );
        }

        if(!empty($this->cookies)) {

            foreach($this->cookies as $cookie) {

                if(substr($cookie->getDomain(), 0, 1) == ".") // Throws error when period on beginning of domain
                    $cookie->setDomain(substr($cookie->getDomain(), 1, strlen($cookie->getDomain()) - 1));

                $this->webDriver()->manage()->addCookie($cookie);
            }
            
            return true;
        }
        
        return false;
    }


    public function setCookies(string $account)
    {

        $this->cookies = [];
        $this->cookies = $this->webDriver()->manage()->getCookies();
        file_put_contents(
            $this->getCookieFileLoc($account),
            serialize($this->cookies)
        );
    }


    public function clearCookies()
    {

        if(file_exists($this->getCookieFileLoc())) {

            unlink($this->getCookieFileLoc());
        }
    }


    public function webDriver()
    {

        if(empty($this->WebDriver)) {

            $this->WebDriver = ChromeDriverLoader::load(
                                $this->user_agent, $this->ip_address);     
        }

        return $this->WebDriver;
    }


    public function runJs(string $code)
    {

        $this->webDriver()->executeScript($code);
    }


    /**
     * TODO: grep and reapalce with selectRadioLabelWithContent(
     */
    public function selectWithContent(string $string)
    {

        try{

            $elements = $this->elementsCssSelector(self::$select_radio_selectors, 5);
        } 
        
        catch(\Exception $e) {

            $elements = $this->elementsCssSelector(self::$select_items_selectors, 5);
        }

        foreach($elements as $element) {

            if(strpos($element->getText(), $string) !== false
            && strpos(strtolower($element->getText()), "dealer") === false) {

                $element->click();
                return;
            }
        }

        $first_word = str_replace(["+"], " ", $string); // Try first word in select
        $first_word = substr(explode(" ", $string)[0], 0, 5);
        if($first_word != $string)
            $this->selectWithContent($first_word);


        throw new \Exception("Item not found in select content");
    }


    public function elementCssSelector(String $selector, int $wait = 25)
    {

        $this->webDriver()->wait($wait)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($selector))
        );    

        return $this->webDriver()->findElement(WebDriverBy::cssSelector($selector));
    }


    public function elementsCssSelector(String $selector, int $wait = 25)
    {

        $this->webDriver()->wait($wait)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($selector))
        );    

        return $this->webDriver()->findElements(WebDriverBy::cssSelector($selector));
    }


    public function cssSelectorOfElement(String $selector, RemoteWebElement $element)
    {

        return $element->findElement(WebDriverBy::cssSelector($selector));
    }


    public function cssSelectorsOfElement(String $selector, RemoteWebElement $element)
    {

        return $element->findElements(WebDriverBy::cssSelector($selector));
    }


    public function fill(string $selector, string $value, int $wait = 25)
    {

        $this->webDriver()->wait($wait)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($selector))
        );     

        $this->webDriver()->findElement(WebDriverBy::cssSelector($selector))
            ->sendKeys($value);
    }


    public function click(string $selector, int $wait = 25)
    {

        $this->webDriver()->wait($wait)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($selector))
        );     

        $this->webDriver()->findElement(WebDriverBy::cssSelector($selector))
            ->click();
    }


    public function titleContains(String $title, int $wait = 25)
    {

        $this->webDriver()->wait($wait)->until(
            WebDriverExpectedCondition::titleContains($title)
        );    

        return true;
    }


    public function urlContains(String $string, int $wait = 25)
    {

        $this->webDriver()->wait($wait)->until(
            WebDriverExpectedCondition::urlContains($string)
        );    

        return true;
    }

    public static $key;
    private static $s = 0;
    public function screenshot(String $name = "screenshot")
    {
        
        if(self::$debug) {

            if(!empty(self::$key) && !is_dir(self::$screenshot_dir . self::$key)) {

                mkdir(self::$screenshot_dir . self::$key);
            }

            $this->WebDriver()->takeScreenshot(self::$screenshot_dir . self::$key . "/" . self::$s++ . ".png");
        }
    }

    public function deleteScreenshots()
    {

        array_map('unlink', glob(self::$screenshot_dir . self::$key . "/*.*"));
        rmdir(self::$screenshot_dir . self::$key);
    }
}