<?php
declare(strict_types=1);

/** Rebuild supported markup so pasted HTML cannot introduce executable content. */
function sanitizeArticleHtml(string $html): string {
    $document=new DOMDocument('1.0','UTF-8');
    $previous=libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>',LIBXML_NONET);
        $output=new DOMDocument('1.0','UTF-8');
        $root=$output->createElement('div'); $output->appendChild($root);
        $copy=function(DOMNode $source,DOMNode $parent,int $depth=0) use(&$copy,$output): void {
            if($depth>100) return;
            if($source instanceof DOMText) { $parent->appendChild($output->createTextNode($source->nodeValue)); return; }
            if(!($source instanceof DOMElement)) return;
            $tag=strtolower($source->tagName);
            if(in_array($tag,['script','style','iframe','object','embed','svg','math','template','form','input','button','textarea','select','noscript','img','video','audio'],true)) return;
            $aliases=['b'=>'strong','i'=>'em','strike'=>'s','div'=>'p','h1'=>'h2','h4'=>'h3','h5'=>'h3','h6'=>'h3'];
            $tag=$aliases[$tag]??$tag;
            $allowed=in_array($tag,['p','br','strong','em','u','s','h2','h3','ul','ol','li','blockquote','a','span','sub','sup'],true);
            $target=$allowed?$output->createElement($tag):$parent;
            if($allowed) {
                if($tag==='a') {
                    $href=trim($source->getAttribute('href'));
                    if(!preg_match('/[\x00-\x20\x7f]/u',$href) && (preg_match('~^https?://~i',$href) || preg_match('~^mailto:[^@]+@[^@]+$~i',$href) || preg_match('~^/(?!/)~',$href) || preg_match('/^#[\p{L}\p{N}_-]+$/u',$href))) {
                        $target->setAttribute('href',$href);
                        $target->setAttribute('rel','noopener noreferrer');
                    }
                }
                $classes=[];
                foreach(preg_split('/\s+/',trim($source->getAttribute('class'))) as $class) if(preg_match('/^ql-(?:align-(?:center|right|justify)|indent-[1-8])$/',$class)) $classes[]=$class;
                if($classes) $target->setAttribute('class',implode(' ',array_unique($classes)));
                $parent->appendChild($target);
            }
            foreach($source->childNodes as $child) $copy($child,$target,$depth+1);
        };
        foreach($document->getElementsByTagName('body')->item(0)->childNodes as $child) $copy($child,$root);
        $result=''; foreach($root->childNodes as $child) $result.=$output->saveHTML($child);
        return trim($result);
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
}
function articleBodyHtml(array $article): string {
    return ($article['body_format']??'text')==='html'?sanitizeArticleHtml($article['body']):nl2br(e($article['body']));
}
function articleBodyText(array $article): string {
    if(($article['body_format']??'text')!=='html') return $article['body'];
    $html=preg_replace('~</(?:p|h2|h3|li|blockquote)>|<br\s*/?>~i',"\n",sanitizeArticleHtml($article['body']));
    return trim(html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}
