<?php
class Video_Tag extends H2o_Node {
    var $term, $cacheKey;

    function is_single_video()
    {
        return (stripos($_SERVER['REQUEST_URI'], 'videos/') !== false) || (stripos($_SERVER['REQUEST_URI'], 'watch/') !== false);
    }

    function __construct($argstring, $parser, $pos=0) {
        list($this->term, $this->hack) = explode(' ', "$argstring ");
    }

    function get_api_url($term = 'hello world', $hack = ""){
        $term .= " ".$hack ;
        $term = urlencode($term);
        return "http://www.bing.com/videos/search?q=$term";
    }

    function fetch($context,$url) {
        $this->url = $url;
        $doc = @file_get_contents($this->url);

        phpQuery::newDocument($doc);
        $videos = array();

        foreach(pq('td.resultCell a') as $item){

            $video['link'] = pq($item)->attr('href');
            $title = pq('div.title span', $item)->attr('title');
            if(empty($title)) {
                $title = strip_tags(pq('div.title', $item)->html());
            }
            $video['title'] = $title;

            $videos[] = $video;

        }

        return $videos;
    }

    function render($context, $stream) {
        $cache = h2o_cache($context->options);
        $term  = $context->resolve(':term');
        $hack  = 'site:youtube.com';

        $url   = $this->get_api_url($term, $hack);
        $feed  = @$this->fetch($context,$url);

        $context->set("videos", $feed);
        $context->set("is_single_video", $this->is_single_video());
    }

}
h2o::addTag('video');