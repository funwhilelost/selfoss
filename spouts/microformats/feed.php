<?php

namespace spouts\microformats;

require_once('./libs/php-mf2/Mf2/Parser.php');
require_once('./libs/php-mf-cleaner/src/BarnabyWalters/Mf2/Functions.php');

use BarnabyWalters\Mf2;

/**
 * Spout for fetching a microformats feed
 *
 * @package    spouts
 * @subpackage rss
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de>
 */
class feed extends \spouts\spout {

    /**
     * name of source
     *
     * @var string
     */
    public $name = 'Microformats Feed';
    
    
    /**
     * description of this source type
     *
     * @var string
     */
    public $description = 'A Microformats h-entry feed as source';

    /**
     * config params
     * array of arrays with name, type, default value, required, validation type
     *
     * - Values for type: text, password, checkbox
     * - Values for validation: alpha, email, numeric, int, alnum, notempty
     * 
     * e.g.
     * array(
     *   "id" => array(
     *     "title"      => "URL",
     *     "type"       => "text",
     *     "default"    => "",
     *     "required"   => true,
     *     "validation" => array("alnum")
     *   ),
     *   ....
     * )
     *
     * @var bool|mixed
     */
    public $params = array(
        "url" => array(
            "title"      => "URL",
            "type"       => "text",
            "default"    => "",
            "required"   => true,
            "validation" => array("notempty")
        )
    );
    
    
    /**
     * current fetched items
     *
     * @var array|bool
     */
    protected $items = false;

    private $url;
    
    
    //
    // Iterator Interface
    //
    
    /**
     * reset iterator
     *
     * @return void
     */
    public function rewind() {
        if($this->items!==false)
            reset($this->items);
    }

    
    /**
     * receive current item
     *
     * @return SimplePie_Item current item
     */
    public function current() {
        if($this->items!==false)
            return $this;
        return false;
    }

    
    /**
     * receive key of current item
     *
     * @return mixed key of current item
     */
    public function key() {
        if($this->items!==false)
            return key($this->items);
        return false;
    }

    
    /**
     * select next item
     *
     * @return SimplePie_Item next item
     */
    public function next() {
        if($this->items!==false)
            next($this->items);
        return $this;
    }

    
    /**
     * end reached
     *
     * @return bool false if end reached
     */
    public function valid() {
        if($this->items!==false)
            return current($this->items) !== false;
        return false;
    }
    
    
    
    //
    // Source Methods
    //
    
    
    /**
     * loads content for given source
     * I supress all Warnings of SimplePie for ensuring
     * working plugin in PHP Strict mode
     *
     * @return void
     * @param mixed $params the params of this source
     */
    public function load($params) {
        // initialize simplepie feed loader

        $url = htmlspecialchars_decode($params['url']);
        $this->url = $url;
        $html = file_get_contents($url);

        @$this->feed = \Mf2\parse($html, $url, false);

        // check for error
        if(@$this->feed == false) {
            throw new \exception('error parsing feed');
        } else {
            // save fetched items
            $this->items = Mf2\findMicroformatsByType(@$this->feed, 'h-entry');
        }

        // return html url
        $this->htmlUrl = htmlspecialchars_decode($params['url']);
    }


    /**
     * returns the global html url for the source
     *
     * @return string url as html
     */
    public function getHtmlUrl() {
        if(isset($this->htmlUrl))
            return $this->htmlUrl;
        return false;
    }
    
    
    /**
     * returns an unique id for this item
     *
     * @return string id as hash
     */
    public function getId() {
        if($this->items!==false && $this->valid()) {
        	$item = current($this->items);
            $id = @$item['properties']['url'][0];
            if(strlen($id)>255)
                $id = md5($id);
            if($id == false) {
	        	$item = current($this->items);
                $id = md5(@$item['properties']['name'][0]);
            }
            return $id;
        }
        return false;
    }
    
    
    /**
     * returns the current title as string
     *
     * @return string title
     */
    public function getTitle() {
        if($this->items!==false && $this->valid())
        	$item = current($this->items);
            return @$item['properties']['name'][0];
        return false;
    }
    
    
    /**
     * returns the content of this item
     *
     * @return string content
     */
    public function getContent() {
        if($this->items!==false && $this->valid())
        	$item = current($this->items);
            return @$item['properties']['content'][0]['html'];
        return false;
    }
    
    
    /**
     * returns the icon of this item
     *
     * @return string icon url
     */
    public function getIcon() {
        if($this->items!==false && $this->valid()) {
        	$item = current($this->items);
            if(array_key_exists('author', $item['properties'])) {
	        	$item = current($this->items);
                return @$item['properties']['author'][0]['properties']['photo'][0];
            } else {
                // No author in the entry itself, so use the authorship discovery algorithm to find the author
                $author = Mf2\getAuthor(current($this->items), $this->feed, $this->url);
                if($author && array_key_exists('photo', $author['properties'])) {
                    return $author['properties']['photo'][0];
                }
            }
        }
        return false;
    }
    
    
    /**
     * returns the link of this item
     *
     * @return string link
     */
    public function getLink() {
        if($this->items!==false && $this->valid())
        	$item = current($this->items);
            return @$item['properties']['url'][0];
        return false;
    }
    
    
    /**
     * returns the date of this item
     *
     * @return string date
     */
    public function getDate() {
        if($this->items!==false && $this->valid()) {
        	$item = current($this->items);
            $date = date('Y-m-d H:i:s', strtotime(@$item['properties']['published'][0]));
        }
        if(strlen($date)==0)
            $date = date('Y-m-d H:i:s');
        return $date;
    }
    
    /**
     * destroy the plugin (prevent memory issues)
     */
    public function destroy() {
        unset($this->items);
        $this->items = false;
    }
}
