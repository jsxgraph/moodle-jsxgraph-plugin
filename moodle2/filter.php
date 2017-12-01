<?php
    /**
     * Version details
     *
     * @package    jsxgraph filter
     * @copyright  2017 Michael Gerhaeuser, Matthias Ehmann, Carsten Miller, Alfred Wassermann <alfred.wassermann@uni-bayreuth.de>, Andreas Walter
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    
    require_once($CFG->libdir . '/pagelib.php');
    
    class filter_jsxgraph extends moodle_text_filter {
        
        /**
         *
         * @get text between tags
         * @param string $tag The tag name
         * @param string $html The XML or XHTML string
         * @param int $strict Whether to use strict mode
         * @param string $encoding
         * @return array
         */
        private function getTextBetweenTags($tag, $html, $strict = 0, $encoding = "UTF-8") {
            global $PAGE, $CFG;
            
            if (!isset($CFG->filter_jsxgraph_divid)) {
                set_config('filter_jsxgraph_divid', 'box');
            }
            if (!isset($CFG->filter_jsxgraph_boardvar)) {
                set_config('filter_jsxgraph_boardvar', 'board');
            }
            if (!isset($CFG->filter_jsxgraph_width)) {
                set_config('filter_jsxgraph_width', '500');
            }
            if (!isset($CFG->filter_jsxgraph_height)) {
                set_config('filter_jsxgraph_height', '400');
            }
            if (!isset($CFG->filter_jsxgraph_HTMLentities)) {
                set_config('filter_jsxgraph_HTMLentities', '1');
            }
            
            // a new dom object
            $dom = new domDocument;
            $dom->formatOutput = true;
            
            // load the html into the object
            if ($strict == 1) {
                $dom->loadXML($html);
            } else {
                libxml_use_internal_errors(true);
                $htmlutf8 = mb_convert_encoding($html, 'HTML-ENTITIES', $encoding);
                $dom->loadHTML($htmlutf8);
                libxml_use_internal_errors(false);
            }
            
            // discard white space
            $dom->preserveWhiteSpace = false;
            $dom->strictErrorChecking = false;
            $dom->recover = true;
            
            // the tag by its tag name
            $content = $dom->getElementsByTagname($tag);
            
            if (count($content) > 0) {
                $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/filter/jsxgraph/jsxgraphcore.js'));
            }
            
            // Iterate backwards through the jsxgraph tags
            $i = $content->length - 1;
            while ($i > -1) {
                $item = $content->item($i);
                
                // Read attributes
                $w = $item->getAttribute('width');
                if ($w == "") {
                    $w = $CFG->filter_jsxgraph_width;
                }
                
                $h = $item->getAttribute('height');
                if ($h == "") {
                    $h = $CFG->filter_jsxgraph_height;
                }
                
                $b = $item->getAttribute('box');
                if ($b == "") {
                    $b = $CFG->filter_jsxgraph_divid . $i;
                }
                
                $brd = $item->getAttribute('board');
                if ($brd == "") {
                    $brd = $CFG->filter_jsxgraph_boardvar . $i;
                }
                
                $convertHTMLentities = $item->getAttribute('htmlentities');
                if ($convertHTMLentities == "") {
                    $convertHTMLentities = $CFG->filter_jsxgraph_HTMLentities;
                }
                $convertHTMLentities = boolval($convertHTMLentities);
                
                // Create new div element containing JSXGraph
                $out = $dom->createElement('div');
                $a = $dom->createAttribute('id');
                $a->value = $b;
                $out->appendChild($a);
                
                $a = $dom->createAttribute('class');
                $a->value = "jxgbox";
                $out->appendChild($a);
                
                $a = $dom->createAttribute('style');
                if (is_numeric($w)) {
                    $w .= "px";
                }
                if (is_numeric($h)) {
                    $h .= "px";
                }
                $a->value = "width:" . $w . "; height:" . $h . "; ";
                $out->appendChild($a);
                
                $t = $dom->createTextNode("");
                $out->appendChild($t);
                
                $out = $dom->appendChild($out);
                
                
                // Replace <jsxgraph>-node by <div>-node
                $item->parentNode->replaceChild($out, $item);
                
                $code = "";
                $needGXT = false;
                $url = $item->getAttribute('file');
                if ($url != "") {
                    $code = "var " . $brd . " = JXG.JSXGraph.loadBoardFromFile('" . $b . "', '" . $url . "', 'Geonext');";
                    $needGXT = true;
                } else {
                    $url = $item->getAttribute('filestring');
                    if ($url != "") {
                        $code = "var " . $brd . " = JXG.JSXGraph.loadBoardFromString('" . $b . "', '" . $url . "', 'Geonext');";
                        $needGXT = true;
                    } else {
                        // Plain JavaScript code
                        
                        // To use MathJax on the board, their filter must already have been replaced code to HTML-Tags
                        $code = $dom->saveHTML($item);
                        // Remove <jsxgraph>-tags
                        $code = preg_replace("(</?" . $tag . "[^>]*\>)i", "", $code);
                        // In order not to terminate the JavaScript part prematurely, the backslash has to be escaped
                        $code = str_replace("</script>", "<\/script>", $code);
                        if ($convertHTMLentities) {
                            // No HTML-Entities in code
                            $code = html_entity_decode($code);
                        }
                    }
                }
                
                /* Ensure that the div exists */
                #$code_pre = "require(['jsxgraphcore'], function (JXG) { if (document.getElementById('" . $b . "') != null) { \n";
                #$code_post = "}\n });\n";
                
                $code = "if (document.getElementById('" . $b . "') != null) {" . $code . "};";
                #$code = $code_pre . $code . $code_post;
                
                // Place JavaScript code at the end of the page.
                $PAGE->requires->js_init_call($code);
                
                if ($needGXT) {
                    $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/filter/jsxgraph/geonext.min.js'));
                }
                
                --$i;
            }
            
            // remove DOCTYPE
            $dom->removeChild($dom->firstChild);
            
            // remove <html><body></body></html>
            $str = $dom->saveHTML();
            $str = str_replace("<body>", "", $str);
            $str = str_replace("</body>", "", $str);
            $str = str_replace("<html>", "", $str);
            $str = str_replace("</html>", "", $str);
            
            return $str;
        }
        
        public function filter($text, array $options = array()) {
            global $PAGE, $CFG;
            
            // to optimize speed, search for a <jsxgraph>-tag (avoiding to parse everything on every text)
            if (!is_int(strpos($text, '<jsxgraph'))) {
                return $text;
            }
            
            return $this->getTextBetweenTags("jsxgraph", $text, 0, "UTF-8");
        }
    }

