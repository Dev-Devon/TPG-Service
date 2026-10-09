<?php
/**
 * PageWright — HTML-to-PDF Document Engine
 * Single-file pure-PHP implementation
 *
 * POST /pagewright.php → PDF binary
 *   Fields: html, css, pageSize, orientation,
 *           marginTop/Right/bottom/left, marginUnit,
 *           headerText, footerText
 *
 * GET  /pagewright.php → Editor interface
 */

// ═══════════════════════════════════════════════════════════
//  SECTION 1: PDF WRITER
// ═══════════════════════════════════════════════════════════

class PdfWriter {
    private $objects = [];
    private $offsets = [];

    public function add($dict, $stream = null) {
        $n = count($this->objects) + 1;
        $this->objects[$n] = ['dict' => $dict, 'stream' => $stream];
        return $n;
    }

    public function ref($n) { return "$n 0 R"; }

    public function output() {
        $buf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        foreach ($this->objects as $n => $o) {
            $this->offsets[$n] = strlen($buf);
            $buf .= "$n 0 obj\n";
            if ($o['stream'] !== null) {
                $s = $o['stream'];
                $d = rtrim($o['dict']);
                if (substr($d, -2) === '>>') {
                    $d = substr($d, 0, -2) . " /Length " . strlen($s) . " >>";
                } else {
                    $d = "<< /Length " . strlen($s) . " >>";
                }
                $buf .= "$d\nstream\n$s\nendstream\n";
            } else {
                $buf .= $o['dict'] . "\n";
            }
            $buf .= "endobj\n";
        }
        $xref = strlen($buf);
        $cnt  = count($this->objects) + 1;
        $buf .= "xref\n0 $cnt\n";
        $buf .= sprintf("%010d 65535 f \n", 0);
        for ($i = 1; $i < $cnt; $i++) {
            $buf .= sprintf("%010d 00000 n \n", $this->offsets[$i]);
        }
        $buf .= "trailer\n<< /Size $cnt /Root {$this->ref(1)} >>\n";
        $buf .= "startxref\n$xref\n%%EOF\n";
        return $buf;
    }
}

// ═══════════════════════════════════════════════════════════
//  SECTION 2: FONT METRICS
// ═══════════════════════════════════════════════════════════

class FontMetrics {
    // Helvetica widths in 1/1000 em (ASCII 32–126)
    private static $hw = [
        278,278,355,556,556,889,667,191,333,333,389,564,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,564,564,564,556,
        667,667,667,667,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,556,278,556,556
    ];
    // Helvetica-Bold widths
    private static $hbw = [
        278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,
        750,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,
        333,611,611,556,611,611,333,611,611,278,278,556,278,889,611,611,
        611,611,389,556,333,611,556,778,556,556,500,556,278,556,556
    ];

    public static function getWidths($font) {
        return $font === 'Helvetica-Bold' ? self::$hbw : self::$hw;
    }

    public static function textWidth($text, $size, $font = 'Helvetica') {
        $w = 0;
        $widths = self::getWidths($font);
        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $c = ord($text[$i]);
            $w += ($c >= 32 && $c <= 126) ? $widths[$c - 32] : 556;
        }
        return $w * $size / 1000;
    }

    public static function pdfFontName($font) {
        return $font === 'Helvetica-Bold' ? 'Helvetica-Bold' : 'Helvetica';
    }
}

// ═══════════════════════════════════════════════════════════
//  SECTION 3: HTML PARSER
// ═══════════════════════════════════════════════════════════

class HtmlNode {
    public $type, $tag, $attrs, $children, $text, $style;
    function __construct($type, $tag = '', $attrs = [], $text = '') {
        $this->type = $type; $this->tag = $tag; $this->attrs = $attrs;
        $this->children = []; $this->text = $text; $this->style = [];
    }
}

class HtmlParser {
    private $pos, $len, $src;
    private static $void = ['br','hr','img','input','meta','link','area','base','col','embed','param','source','track','wbr'];

    public function parse($html) {
        $this->src = $html; $this->pos = 0; $this->len = strlen($html);
        $root = new HtmlNode('element', 'body');
        $this->contents($root, ['body','html']);
        return $root;
    }

    private function contents($parent, $stop) {
        while ($this->pos < $this->len) {
            $this->ws();
            if ($this->pos >= $this->len) break;
            if ($this->src[$this->pos] === '<') {
                if (substr($this->src, $this->pos, 4) === '<!--') {
                    $e = strpos($this->src, '-->', $this->pos + 4);
                    $this->pos = $e !== false ? $e + 3 : $this->len;
                    continue;
                }
                if ($this->pos + 1 < $this->len && $this->src[$this->pos + 1] === '/') break;
                $t = $this->tag();
                if (!$t) continue;
                if (in_array($t['tag'], $stop)) { $this->cTag(); break; }
                $node = new HtmlNode('element', $t['tag'], $t['attrs']);
                if (!in_array($t['tag'], self::$void) && !$t['sc']) {
                    $this->contents($node, [$t['tag']]);
                    $this->cTag();
                }
                $parent->children[] = $node;
            } else {
                $tx = $this->text();
                if (trim($tx) !== '') $parent->children[] = new HtmlNode('text', '', [], $tx);
            }
        }
    }

    private function tag() {
        $this->pos++; $this->ws();
        $name = '';
        while ($this->pos < $this->len && ctype_alnum($this->src[$this->pos]))
            $name .= $this->src[$this->pos++];
        $name = strtolower($name);
        if (!$name) { $this->pos++; return null; }
        $attrs = []; $sc = false;
        while ($this->pos < $this->len) {
            $this->ws();
            if ($this->pos >= $this->len) break;
            if ($this->src[$this->pos] === '/' && $this->pos + 1 < $this->len && $this->src[$this->pos + 1] === '>') {
                $sc = true; $this->pos += 2; break;
            }
            if ($this->src[$this->pos] === '>') { $this->pos++; break; }
            $k = '';
            while ($this->pos < $this->len && preg_match('/[a-zA-Z0-9\-_]/', $this->src[$this->pos]))
                $k .= $this->src[$this->pos++];
            $this->ws(); $v = '';
            if ($this->pos < $this->len && $this->src[$this->pos] === '=') {
                $this->pos++; $this->ws();
                if ($this->pos < $this->len && $this->src[$this->pos] === '"') {
                    $this->pos++;
                    while ($this->pos < $this->len && $this->src[$this->pos] !== '"') $v .= $this->src[$this->pos++];
                    if ($this->pos < $this->len) $this->pos++;
                } elseif ($this->pos < $this->len && $this->src[$this->pos] === "'") {
                    $this->pos++;
                    while ($this->pos < $this->len && $this->src[$this->pos] !== "'") $v .= $this->src[$this->pos++];
                    if ($this->pos < $this->len) $this->pos++;
                } else {
                    while ($this->pos < $this->len && !preg_match('/[\s>\/]/', $this->src[$this->pos])) $v .= $this->src[$this->pos++];
                }
            }
            if ($k) $attrs[$k] = $v;
        }
        return ['tag' => $name, 'attrs' => $attrs, 'sc' => $sc];
    }

    private function cTag() {
        if ($this->pos >= $this->len || $this->src[$this->pos] !== '<') return;
        if ($this->pos + 1 < $this->len && $this->src[$this->pos + 1] === '/') {
            $e = strpos($this->src, '>', $this->pos);
            $this->pos = $e !== false ? $e + 1 : $this->len;
        }
    }

    private function text() {
        $t = '';
        while ($this->pos < $this->len && $this->src[$this->pos] !== '<') $t .= $this->src[$this->pos++];
        return $t;
    }

    private function ws() {
        while ($this->pos < $this->len && ctype_space($this->src[$this->pos])) $this->pos++;
    }
}

// ═══════════════════════════════════════════════════════════
//  SECTION 4: CSS PARSER
// ═══════════════════════════════════════════════════════════

class CssParser {
    public $rules = [];

    public function parse($css) {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $p = 0; $l = strlen($css);
        while ($p < $l) {
            $s = $p;
            while ($p < $l && $css[$p] !== '{') $p++;
            if ($p >= $l) break;
            $sel = trim(substr($css, $s, $p - $s));
            $p++; $ds = $p; $d = 1;
            while ($p < $l && $d > 0) { if ($css[$p]==='{') $d++; elseif ($css[$p]==='}') $d--; $p++; }
            $dt = trim(substr($css, $ds, $p - $ds - 1));
            $props = [];
            foreach (explode(';', $dt) as $dec) {
                $dec = trim($dec); if (!$dec) continue;
                $c = strpos($dec, ':'); if ($c === false) continue;
                $k = trim(substr($dec, 0, $c)); $v = trim(substr($dec, $c + 1));
                if ($k && $v) $props[$k] = $v;
            }
            if ($sel && $props) $this->rules[] = ['sel' => $sel, 'spec' => $this->spec($sel), 'props' => $props];
        }
        usort($this->rules, fn($a,$b) => $a['spec'] - $b['spec']);
    }

    private function spec($s) {
        $v = substr_count($s,'#')*100 + substr_count($s,'.')*10;
        foreach (preg_split('/\s+/', trim($s)) as $p) {
            $p = ltrim($p, '.#:');
            if ($p && ctype_alpha($p[0])) $v += 1;
        }
        return $v;
    }

    public function match(HtmlNode $node) {
        if ($node->type !== 'element') return [];
        $m = [];
        foreach ($this->rules as $r) {
            if ($this->fits($r['sel'], $node)) foreach ($r['props'] as $k => $v) $m[$k] = $v;
        }
        if (isset($node->attrs['style'])) {
            foreach (explode(';', $node->attrs['style']) as $d) {
                $c = strpos($d, ':'); if ($c === false) continue;
                $m[trim(substr($d,0,$c))] = trim(substr($d,$c+1));
            }
        }
        return $m;
    }

    private function fits($sel, HtmlNode $n) {
        $parts = preg_split('/\s+/', trim($sel));
        $last = $parts[count($parts)-1];
        if ($last[0] === '#') return isset($n->attrs['id']) && $n->attrs['id'] === substr($last,1);
        if ($last[0] === '.') { $cl = substr($last,1); return isset($n->attrs['class']) && in_array($cl, explode(' ', $n->attrs['class'])); }
        return $n->tag === strtolower($last);
    }
}

// ═══════════════════════════════════════════════════════════
//  SECTION 5: PAGEWRIGHT ENGINE
// ═══════════════════════════════════════════════════════════

class PageWright {
    // Page sizes in mm
    const SIZES = [
        'A4'     => [210, 297], 'A3' => [297, 420], 'A5' => [148, 210],
        'Letter' => [215.9, 279.4], 'Legal' => [215.9, 355.6]
    ];

    private $settings;
    private $pageW, $pageH;       // pt
    private $mT, $mR, $mB, $mL;   // pt
    private $contentW, $contentH;  // pt
    private $css;

    // Default element styles (pt)
    private $defaults = [
        'h1' => ['font-size'=>22,'font-weight'=>'bold','margin-top'=>14,'margin-bottom'=>6,'line-height'=>1.2],
        'h2' => ['font-size'=>16,'font-weight'=>'bold','margin-top'=>12,'margin-bottom'=>5,'line-height'=>1.3],
        'h3' => ['font-size'=>13,'font-weight'=>'bold','margin-top'=>10,'margin-bottom'=>4,'line-height'=>1.3],
        'h4' => ['font-size'=>11,'font-weight'=>'bold','margin-top'=>8,'margin-bottom'=>3,'line-height'=>1.4],
        'p'  => ['font-size'=>10,'margin-bottom'=>6,'line-height'=>1.5],
        'table'=>['font-size'=>9,'margin-bottom'=>8,'margin-top'=>4],
        'th' => ['font-weight'=>'bold','padding'=>3],
        'td' => ['padding'=>3],
        'ul' => ['font-size'=>10,'margin-bottom'=>6,'margin-left'=>18,'line-height'=>1.5],
        'ol' => ['font-size'=>10,'margin-bottom'=>6,'margin-left'=>18,'line-height'=>1.5],
        'li' => ['margin-bottom'=>2],
        'hr' => ['margin-top'=>6,'margin-bottom'=>6],
        'blockquote'=>['font-size'=>10,'margin-left'=>20,'margin-bottom'=>6,'line-height'=>1.5],
    ];

    public function convert($html, $css, $settings) {
        $this->settings = $settings;
        $this->setupPage();

        // Parse CSS
        $this->css = new CssParser();
        if ($css) $this->css->parse($css);

        // Parse HTML
        $parser = new HtmlParser();
        $dom = $parser->parse($html);

        // Layout: produce a flat list of layout blocks
        $blocks = $this->layout($dom);

        // Paginate: split into pages
        $pages = $this->paginate($blocks);

        // Render: produce PDF
        return $this->renderPdf($pages);
    }

    // ── Page setup ──
    private function setupPage() {
        $s = $this->settings;
        $sz = self::SIZES[$s['pageSize']] ?? self::SIZES['A4'];
        $pw = $sz[0]; $ph = $sz[1];
        if ($s['orientation'] === 'landscape') { $t = $pw; $pw = $ph; $ph = $t; }

        // Convert mm → pt (1 mm = 2.8346 pt)
        $this->pageW = $pw * 2.8346;
        $this->pageH = $ph * 2.8346;

        // Margins
        $u = $s['marginUnit'] ?? 'mm';
        $c = $u === 'mm' ? 2.8346 : ($u === 'in' ? 72 : 1);
        $this->mT = ($s['marginTop'] ?? 20) * $c;
        $this->mR = ($s['marginRight'] ?? 15) * $c;
        $this->mB = ($s['marginBottom'] ?? 20) * $c;
        $this->mL = ($s['marginLeft'] ?? 15) * $c;

        $this->contentW = $this->pageW - $this->mL - $this->mR;
        $this->contentH = $this->pageH - $this->mT - $this->mB;
    }

    // ── Layout: walk DOM → flat block list ──
    private function layout($dom) {
        $blocks = [];
        $this->layoutNode($dom, $blocks, $this->defaults['p']);
        return $blocks;
    }

    private function layoutNode($node, &$blocks, $parentStyle) {
        if ($node->type === 'text') {
            // Inline text — will be collected by parent
            return;
        }

        $tag = $node->tag;
        $def = $this->defaults[$tag] ?? [];
        $matched = $this->css->match($node);

        // Merge styles: defaults → parent → matched
        $style = array_merge($parentStyle, $def, $matched);
        $node->style = $style;

        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4':
            case 'p': case 'blockquote':
                $this->layoutParagraph($node, $blocks, $style);
                break;
            case 'ul': case 'ol':
                $this->layoutList($node, $blocks, $style, $tag === 'ol');
                break;
            case 'table':
                $this->layoutTable($node, $blocks, $style);
                break;
            case 'hr':
                $blocks[] = ['type' => 'hr', 'style' => $style, 'height' => 1, 'margin_top' => $style['margin-top'] ?? 6, 'margin_bottom' => $style['margin-bottom'] ?? 6];
                break;
            case 'img':
                // Basic image support: placeholder box
                $w = ptVal($style['width'] ?? 0) ?: 100;
                $h = ptVal($style['height'] ?? 0) ?: 75;
                $blocks[] = ['type' => 'image', 'style' => $style, 'width' => $w, 'height' => $h, 'margin_top' => 4, 'margin_bottom' => 4];
                break;
            default:
                // Generic container: recurse into children
                foreach ($node->children as $child) {
                    $this->layoutNode($child, $blocks, $style);
                }
                break;
        }
    }

    private function collectText($node) {
        if ($node->type === 'text') return $node->text;
        $t = '';
        foreach ($node->children as $c) {
            $ct = $this->collectText($c);
            if ($ct !== '') {
                if ($t !== '' && substr($t, -1) !== ' ') $t .= ' ';
                $t .= $ct;
            }
        }
        return $t;
    }

    private function layoutParagraph($node, &$blocks, $style) {
        $text = trim($this->collectText($node));
        if ($text === '') return;

        $fontSize = ptVal($style['font-size'] ?? 10);
        $lineH = $fontSize * ($style['line-height'] ?? 1.4);
        $font = ($style['font-weight'] ?? '') === 'bold' ? 'Helvetica-Bold' : 'Helvetica';
        $color = parseColor($style['color'] ?? '#1a1a1a');
        $align = $style['text-align'] ?? 'left';
        $indent = ptVal($style['text-indent'] ?? 0);

        // Word-wrap
        $lines = $this->wrapText($text, $fontSize, $lineH, $font, $this->contentW - $indent);
        if ($indent > 0 && !empty($lines)) {
            $lines[0]['indent'] = $indent;
        }

        $totalH = array_sum(array_column($lines, 'height'));

        $blocks[] = [
            'type'        => $node->tag,
            'style'       => $style,
            'lines'       => $lines,
            'font'        => $font,
            'fontSize'    => $fontSize,
            'color'       => $color,
            'align'       => $align,
            'height'      => $totalH,
            'margin_top'  => $style['margin-top'] ?? 0,
            'margin_bottom' => $style['margin-bottom'] ?? 6,
            'keepWithNext' => in_array($node->tag, ['h1','h2','h3','h4']),
        ];
    }

    private function layoutList($node, &$blocks, $style, $ordered) {
        $fontSize = ptVal($style['font-size'] ?? 10);
        $lineH    = $fontSize * ($style['line-height'] ?? 1.5);
        $font     = ($style['font-weight'] ?? '') === 'bold' ? 'Helvetica-Bold' : 'Helvetica';
        $color    = parseColor($style['color'] ?? '#1a1a1a');
        $mLeft    = ptVal($style['margin-left'] ?? 18);
        $counter  = intval($node->attrs['start'] ?? 1);

        foreach ($node->children as $child) {
            if ($child->type !== 'element' || $child->tag !== 'li') continue;
            $text = trim($this->collectText($child));
            if ($text === '') continue;

            $marker = $ordered ? $counter . '.' : '•';
            $counter++;

            $lines = $this->wrapText($text, $fontSize, $lineH, $font, $this->contentW - $mLeft - 12);

            $blocks[] = [
                'type'        => 'li',
                'style'       => $style,
                'lines'       => $lines,
                'marker'      => $marker,
                'markerWidth' => FontMetrics::textWidth($marker, $fontSize, $font),
                'font'        => $font,
                'fontSize'    => $fontSize,
                'color'       => $color,
                'height'      => array_sum(array_column($lines, 'height')),
                'margin_top'  => 0,
                'margin_bottom' => $style['margin-bottom'] ?? 2,
                'listIndent'  => $mLeft,
                'keepWithNext' => false,
            ];
        }
    }

    private function layoutTable($node, &$blocks, $style) {
        $fontSize = ptVal($style['font-size'] ?? 9);
        $lineH    = $fontSize * 1.3;
        $color    = parseColor($style['color'] ?? '#1a1a1a');

        // Extract rows
        $rows = [];
        $hasHeader = false;
        foreach ($node->children as $section) {
            if ($section->type !== 'element') continue;
            if ($section->tag === 'thead') $hasHeader = true;
            if (in_array($section->tag, ['thead','tbody','tfoot'])) {
                foreach ($section->children as $row) {
                    if ($row->type === 'element' && $row->tag === 'tr') {
                        $cells = [];
                        $isHeader = $section->tag === 'thead';
                        foreach ($row->children as $cell) {
                            if ($cell->type === 'element' && in_array($cell->tag, ['th','td'])) {
                                $cs = $this->css->match($cell);
                                $isH = $isHeader || $cell->tag === 'th';
                                $cells[] = [
                                    'text'    => trim($this->collectText($cell)),
                                    'header'  => $isH,
                                    'bold'    => $isH || ($cs['font-weight'] ?? '') === 'bold',
                                    'bg'      => $isH ? [0.96,0.96,0.96] : parseColor($cs['background-color'] ?? 'transparent'),
                                    'align'   => $cs['text-align'] ?? ($isH ? 'left' : 'left'),
                                ];
                            }
                        }
                        $rows[] = $cells;
                    }
                }
            } elseif ($section->tag === 'tr') {
                $cells = [];
                foreach ($section->children as $cell) {
                    if ($cell->type === 'element' && in_array($cell->tag, ['th','td'])) {
                        $cs = $this->css->match($cell);
                        $isH = $cell->tag === 'th';
                        $cells[] = [
                            'text'   => trim($this->collectText($cell)),
                            'header' => $isH,
                            'bold'   => $isH || ($cs['font-weight'] ?? '') === 'bold',
                            'bg'     => $isH ? [0.96,0.96,0.96] : parseColor($cs['background-color'] ?? 'transparent'),
                            'align'  => $cs['text-align'] ?? 'left',
                        ];
                    }
                }
                $rows[] = $cells;
            }
        }

        if (empty($rows)) return;

        // Calculate column count and widths
        $maxCols = max(array_map('count', $rows));
        $colW = $this->contentW / $maxCols;
        $pad = ptVal($style['padding'] ?? 3);

        // Measure row heights
        $measuredRows = [];
        foreach ($rows as $cells) {
            $rowH = $lineH;
            $mCells = [];
            for ($i = 0; $i < count($cells); $i++) {
                $c = $cells[$i];
                $f = $c['bold'] ? 'Helvetica-Bold' : 'Helvetica';
                $cw = $colW - 2 * $pad;
                $lines = $this->wrapText($c['text'], $fontSize, $lineH, $f, $cw);
                $h = array_sum(array_column($lines, 'height')) + 2 * $pad;
                if ($h > $rowH) $rowH = $h;
                $mCells[] = array_merge($c, ['lines' => $lines, 'font' => $f]);
            }
            // Pad to maxCols
            while (count($mCells) < $maxCols) $mCells[] = ['text'=>'','header'=>false,'bold'=>false,'bg'=>[1,1,1],'align'=>'left','lines'=>[],'font'=>'Helvetica'];
            $measuredRows[] = ['cells' => $mCells, 'height' => $rowH];
        }

        $blocks[] = [
            'type'        => 'table',
            'style'       => $style,
            'rows'        => $measuredRows,
            'colW'        => $colW,
            'maxCols'     => $maxCols,
            'fontSize'    => $fontSize,
            'lineH'       => $lineH,
            'color'       => $color,
            'pad'         => $pad,
            'height'      => array_sum(array_column($measuredRows, 'height')),
            'margin_top'  => $style['margin-top'] ?? 4,
            'margin_bottom' => $style['margin-bottom'] ?? 8,
            'keepWithNext' => false,
        ];
    }

    // ── Text wrapping ──
    private function wrapText($text, $fontSize, $lineH, $font, $maxW) {
        $words = preg_split('/\s+/', $text);
        $lines = []; $cur = ''; $curW = 0;
        $spW = FontMetrics::textWidth(' ', $fontSize, $font);

        foreach ($words as $word) {
            if ($word === '') continue;
            $wW = FontMetrics::textWidth($word, $fontSize, $font);
            if ($cur === '') {
                $cur = $word; $curW = $wW;
            } elseif ($curW + $spW + $wW <= $maxW) {
                $cur .= ' ' . $word; $curW += $spW + $wW;
            } else {
                $lines[] = ['text' => $cur, 'width' => $curW, 'height' => $lineH, 'indent' => 0];
                $cur = $word; $curW = $wW;
            }
        }
        if ($cur !== '') $lines[] = ['text' => $cur, 'width' => $curW, 'height' => $lineH, 'indent' => 0];
        if (empty($lines)) $lines[] = ['text' => '', 'width' => 0, 'height' => $lineH, 'indent' => 0];
        return $lines;
    }

    // ── Pagination ──
    private function paginate($blocks) {
        $pages = []; $pageY = 0; $pageItems = [];

        foreach ($blocks as $i => $block) {
            $mt = $block['margin_top'] ?? 0;
            $mb = $block['margin_bottom'] ?? 0;
            $totalH = $mt + $block['height'] + $mb;

            // Check if block fits
            if ($pageY + $totalH > $this->contentH && !empty($pageItems)) {
                // Start new page
                $pages[] = $pageItems;
                $pageItems = []; $pageY = 0;
            }

            // Handle keepWithNext: peek ahead
            if (!empty($block['keepWithNext']) && isset($blocks[$i + 1])) {
                $next = $blocks[$i + 1];
                $nextH = ($next['margin_top'] ?? 0) + $next['height'] + ($next['margin_bottom'] ?? 0);
                if ($pageY + $totalH + $nextH > $this->contentH && !empty($pageItems)) {
                    $pages[] = $pageItems;
                    $pageItems = []; $pageY = 0;
                }
            }

            // Split paragraph if it doesn't fit
            if ($block['type'] !== 'table' && $block['type'] !== 'hr' && $pageY + $totalH > $this->contentH) {
                $remaining = $this->contentH - $pageY - $mt;
                if ($remaining > 0 && isset($block['lines'])) {
                    // Split lines
                    $split = $this->splitBlock($block, $remaining);
                    if ($split) {
                        $pageItems[] = array_merge($split['first'], ['y' => $pageY + $mt]);
                        $pages[] = $pageItems;
                        $pageItems = []; $pageY = 0;

                        // Place remainder on new page(s)
                        $rest = $split['rest'];
                        while ($rest['height'] > $this->contentH) {
                            $sp = $this->splitBlock($rest, $this->contentH);
                            if (!$sp) break;
                            $pageItems[] = array_merge($sp['first'], ['y' => 0]);
                            $pages[] = $pageItems;
                            $pageItems = []; $pageY = 0;
                            $rest = $sp['rest'];
                        }
                        $pageItems[] = array_merge($rest, ['y' => 0]);
                        $pageY = $rest['height'] + ($rest['margin_bottom'] ?? 0);
                        continue;
                    }
                }
                // Can't split — new page
                $pages[] = $pageItems;
                $pageItems = []; $pageY = 0;
            }

            $pageItems[] = array_merge($block, ['y' => $pageY + $mt]);
            $pageY += $totalH;
        }

        if (!empty($pageItems)) $pages[] = $pageItems;
        return $pages;
    }

    private function splitBlock($block, $availH) {
        if (!isset($block['lines'])) return null;
        $firstLines = []; $restLines = []; $h = 0; $split = false;
        foreach ($block['lines'] as $line) {
            if (!$split && $h + $line['height'] <= $availH) {
                $firstLines[] = $line; $h += $line['height'];
            } else {
                $split = true; $restLines[] = $line;
            }
        }
        if (empty($firstLines) || empty($restLines)) return null;
        $first = $block; $first['lines'] = $firstLines; $first['height'] = array_sum(array_column($firstLines,'height')); $first['margin_top'] = 0; $first['margin_bottom'] = 0;
        $rest = $block; $rest['lines'] = $restLines; $rest['height'] = array_sum(array_column($restLines,'height')); $rest['margin_top'] = 0; $rest['margin_bottom'] = $block['margin_bottom'] ?? 0;
        return ['first' => $first, 'rest' => $rest];
    }

    // ── PDF Rendering ──
    private function renderPdf($pages) {
        $pw = new PdfWriter();

        // Obj 1: Catalog
        $catalogN = $pw->add("<< /Type /Catalog /Pages {$pw->ref(2)} >>");

        // Obj 2: Pages (placeholder, update later)
        $pagesN = $pw->add('');

        // Font objects
        $fontHN = $pw->add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
        $fontBN = $pw->add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");

        $totalPages = count($pages);
        $pageObjs = [];

        foreach ($pages as $pi => $items) {
            $stream = $this->renderPage($items, $pi, $totalPages);
            $contentN = $pw->add("<< >>", $stream);

            $resources = "<< /Font << /F1 {$pw->ref($fontHN)} /F2 {$pw->ref($fontBN)} >> >>";
            $pageN = $pw->add("<< /Type /Page /Parent {$pw->ref($pagesN)} /MediaBox [0 0 " . round($this->pageW,2) . " " . round($this->pageH,2) . "] /Contents {$pw->ref($contentN)} /Resources $resources >>");
            $pageObjs[] = $pageN;
        }

        // Update Pages object
        $kids = implode(' ', array_map(fn($n) => $pw->ref($n), $pageObjs));
        $pw->objects[$pagesN] = ['dict' => "<< /Type /Pages /Kids [$kids] /Count $totalPages >>", 'stream' => null];

        return $pw->output();
    }

    private function renderPage($items, $pageIndex, $totalPages) {
        $s = ''; // content stream
        $mL = $this->mL;
        $pageH = $this->pageH;
        $contentW = $this->contentW;

        // Header
        $header = $this->settings['headerText'] ?? '';
        if ($header !== '') {
            $hy = $pageH - $this->mT + 12;
            $s .= $this->pdfText($mL, $hy, $header, 'F1', 8, [0.5,0.5,0.5]);
        }

        // Footer
        $footer = $this->settings['footerText'] ?? '';
        if ($footer !== '') {
            $footer = str_replace(['{{n}}','{{total}}'], [$pageIndex+1, $totalPages], $footer);
            $fy = $this->mB - 12;
            $s .= $this->pdfText($mL, $fy, $footer, 'F1', 8, [0.5,0.5,0.5]);
        }

        // Render items
        foreach ($items as $item) {
            $y = $pageH - $this->mT - $item['y'];
            $type = $item['type'];

            switch ($type) {
                case 'h1': case 'h2': case 'h3': case 'h4':
                case 'p': case 'blockquote':
                    $s .= $this->renderParagraph($item, $mL, $y);
                    break;
                case 'li':
                    $s .= $this->renderListItem($item, $mL, $y);
                    break;
                case 'table':
                    $s .= $this->renderTable($item, $mL, $y);
                    break;
                case 'hr':
                    $s .= $this->renderHr($item, $mL, $y);
                    break;
            }
        }

        return $s;
    }

    private function renderParagraph($item, $x, $y) {
        $s = '';
        $font = ($item['font'] ?? 'Helvetica') === 'Helvetica-Bold' ? 'F2' : 'F1';
        $fs   = $item['fontSize'] ?? 10;
        $col  = $item['color'] ?? [0,0,0];
        $align= $item['align'] ?? 'left';
        $lineH = ($item['lines'][0]['height'] ?? $fs * 1.4);

        // Background for blockquote
        if ($item['type'] === 'blockquote') {
            $bg = parseColor($item['style']['background-color'] ?? 'transparent');
            if ($bg[0] < 1 || $bg[1] < 1 || $bg[2] < 1) {
                $s .= sprintf("%.3f %.3f %.3f rg\n", $bg[0], $bg[1], $bg[2]);
                $s .= sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $y - $item['height'], $this->contentW, $item['height']);
            }
        }

        foreach ($item['lines'] as $line) {
            $indent = $line['indent'] ?? 0;
            $lx = $x + $indent;

            if ($align === 'center') {
                $lx = $x + ($this->contentW - $line['width']) / 2;
            } elseif ($align === 'right') {
                $lx = $x + $this->contentW - $line['width'];
            } elseif ($align === 'justify') {
                // Justify: not last line
                // (simplified: left-align for now)
            }

            $s .= $this->pdfText($lx, $y, $line['text'], $font, $fs, $col);
            $y -= $line['height'];
        }
        return $s;
    }

    private function renderListItem($item, $x, $y) {
        $s = '';
        $font = ($item['font'] ?? 'Helvetica') === 'Helvetica-Bold' ? 'F2' : 'F1';
        $fs   = $item['fontSize'] ?? 10;
        $col  = $item['color'] ?? [0,0,0];
        $li   = $item['listIndent'] ?? 18;

        // Marker
        $s .= $this->pdfText($x + $li - $item['markerWidth'] - 4, $y, $item['marker'], $font, $fs, $col);

        // Lines
        foreach ($item['lines'] as $line) {
            $s .= $this->pdfText($x + $li, $y, $line['text'], $font, $fs, $col);
            $y -= $line['height'];
        }
        return $s;
    }

    private function renderTable($item, $x, $y) {
        $s = '';
        $fs   = $item['fontSize'] ?? 9;
        $lineH= $item['lineH'] ?? $fs * 1.3;
        $col  = $item['color'] ?? [0,0,0];
        $colW = $item['colW'];
        $pad  = $item['pad'];
        $maxC = $item['maxCols'];
        $rows = $item['rows'];

        foreach ($rows as $ri => $row) {
            $rowH = $row['height'];
            $ry = $y - $rowH;

            // Cell backgrounds and borders
            for ($ci = 0; $ci < $maxC; $ci++) {
                $cell = $row['cells'][$ci];
                $cx = $x + $ci * $colW;

                // Background
                $bg = $cell['bg'];
                if ($bg[0] < 1 || $bg[1] < 1 || $bg[2] < 1) {
                    $s .= sprintf("%.3f %.3f %.3f rg\n", $bg[0], $bg[1], $bg[2]);
                    $s .= sprintf("%.2f %.2f %.2f %.2f re f\n", $cx, $ry, $colW, $rowH);
                }

                // Border
                $s .= sprintf("0.8 0.8 0.8 RG 0.5 w %.2f %.2f %.2f %.2f re S\n", $cx, $ry, $colW, $rowH);

                // Text
                $cf = $cell['font'] === 'Helvetica-Bold' ? 'F2' : 'F1';
                $cy = $y - $pad - $lineH + ($lineH - $fs) / 2;
                foreach ($cell['lines'] as $cl) {
                    $tx = $cx + $pad;
                    $ca = $cell['align'] ?? 'left';
                    if ($ca === 'center') $tx = $cx + ($colW - $cl['width']) / 2;
                    elseif ($ca === 'right') $tx = $cx + $colW - $pad - $cl['width'];
                    $s .= $this->pdfText($tx, $cy, $cl['text'], $cf, $fs, $col);
                    $cy -= $cl['height'];
                }
            }

            $y -= $rowH;
        }
        return $s;
    }

    private function renderHr($item, $x, $y) {
        return sprintf("0 0 0 RG 0.5 w %.2f %.2f m %.2f %.2f l S\n", $x, $y, $x + $this->contentW, $y);
    }

    private function pdfText($x, $y, $text, $font, $size, $col) {
        $s  = "BT\n";
        $s .= "/$font " . round($size, 2) . " Tf\n";
        $s .= sprintf("%.3f %.3f %.3f rg\n", $col[0], $col[1], $col[2]);
        $s .= sprintf("%.2f %.2f Td\n", $x, $y);
        $s .= "(" . $this->esc($text) . ") Tj\n";
        $s .= "ET\n";
        return $s;
    }

    private function esc($t) {
        return str_replace(['\\','(',')'], ['\\\\','\(','\)'], $t);
    }
}

// ═══════════════════════════════════════════════════════════
//  SECTION 6: UTILITIES
// ═══════════════════════════════════════════════════════════

function ptVal($v) {
    if (is_numeric($v)) return floatval($v);
    $v = trim($v);
    if (preg_match('/^([0-9.]+)\s*(pt|px|mm|in|em|rem|%)$/i', $v, $m)) {
        $n = floatval($m[1]); $u = strtolower($m[2]);
        switch ($u) {
            case 'pt': return $n;
            case 'px': return $n * 0.75;
            case 'mm': return $n * 2.8346;
            case 'in': return $n * 72;
            case 'em': case 'rem': return $n * 12; // approx
            case '%':  return $n; // caller must contextualize
        }
    }
    return floatval($v);
}

function parseColor($c) {
    $c = strtolower(trim($c));
    if ($c === 'transparent' || $c === 'none' || $c === '') return [1,1,1];
    if (preg_match('/^#([0-9a-f]{3})$/', $c, $m))
        return [hexdec($m[1][0].$m[1][0])/255, hexdec($m[1][1].$m[1][1])/255, hexdec($m[1][2].$m[1][2])/255];
    if (preg_match('/^#([0-9a-f]{6})$/', $c, $m))
        return [hexdec(substr($m[1],0,2))/255, hexdec(substr($m[1],2,2))/255, hexdec(substr($m[1],4,2))/255];
    if (preg_match('/^rgb\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)\s*\)$/', $c, $m))
        return [floatval($m[1])/255, floatval($m[1])/255, floatval($m[3])/255];
    $named = [
        'black'=>[0,0,0],'white'=>[1,1,1],'red'=>[1,0,0],'green'=>[0,0.502,0],'blue'=>[0,0,1],
        'gray'=>[0.502,0.502,0.502],'grey'=>[0.502,0.502,0.502],'yellow'=>[1,1,0],
        'orange'=>[1,0.647,0],'purple'=>[0.502,0,0.502],'navy'=>[0,0,0.502],
    ];
    return $named[$c] ?? [0,0,0];
}

// ═══════════════════════════════════════════════════════════
//  SECTION 7: ENTRY POINT
// ═══════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $html = $_POST['html'] ?? '';
        $css  = $_POST['css']  ?? '';
        $settings = [
            'pageSize'     => $_POST['pageSize']     ?? 'A4',
            'orientation'  => $_POST['orientation']  ?? 'portrait',
            'marginTop'    => floatval($_POST['marginTop']    ?? 20),
            'marginRight'  => floatval($_POST['marginRight']  ?? 15),
            'marginBottom' => floatval($_POST['marginBottom'] ?? 20),
            'marginLeft'   => floatval($_POST['marginLeft']   ?? 15),
            'marginUnit'   => $_POST['marginUnit']   ?? 'mm',
            'headerText'   => $_POST['headerText']   ?? '',
            'footerText'   => $_POST['footerText']   ?? '',
        ];

        $engine = new PageWright();
        $pdf    = $engine->convert($html, $css, $settings);

        $filename = 'document.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-cache');
        echo $pdf;
        exit;

    } catch (\Throwable $e) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain');
        echo "PDF generation failed: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
        exit;
    }
}

// ═══════════════════════════════════════════════════════════
//  SECTION 8: FRONTEND (served on GET)
// ═══════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PageWright — HTML to PDF</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⬡</text></svg>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Serif:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'IBM Plex Mono',monospace;font-size:13px;line-height:1.5;color:#333;background:#F3F3F3;-webkit-font-smoothing:antialiased}
    .sheet{display:flex;justify-content:center;min-height:100vh;padding:48px 24px}
    .sheet__inner{width:100%;max-width:1200px}
    .runhead{display:flex;justify-content:space-between;align-items:center;padding-bottom:24px;border-bottom:1px solid #E5E5E5;margin-bottom:24px}
    .runhead__brand{font-size:13px;font-weight:500;letter-spacing:.05em;text-transform:uppercase}
    .titleblock{margin-bottom:32px}
    .titleblock h1{font-family:'IBM Plex Serif',serif;font-size:36px;font-weight:600;line-height:1.2;letter-spacing:-.025em;margin-bottom:8px}
    .deck{font-size:14px;color:#666;max-width:700px}
    .ruler{height:1px;background:#000;margin-bottom:32px}
    .workspace{margin-bottom:48px}
    .plate{background:#fff;border:1px solid #E5E5E5;border-radius:4px;overflow:hidden}
    .plate__header{padding:16px 24px;border-bottom:1px solid #E5E5E5;font-size:12px;font-weight:500;letter-spacing:.05em;text-transform:uppercase;color:#666}
    .split-view{display:grid;grid-template-columns:1fr 1fr;min-height:520px}
    .editor-panel{border-right:1px solid #E5E5E5;display:flex;flex-direction:column}
    .editor-tabs{display:flex;border-bottom:1px solid #E5E5E5;background:#FAFAFA}
    .editor-tab{font-family:'IBM Plex Mono',monospace;font-size:11px;font-weight:500;letter-spacing:.05em;text-transform:uppercase;padding:10px 20px;border:none;background:transparent;color:#999;cursor:pointer;transition:color .3s,background .3s;border-bottom:2px solid transparent}
    .editor-tab:hover{color:#666}
    .editor-tab.active{color:#333;background:#fff;border-bottom-color:#333}
    .editor-area{flex:1;position:relative;display:none}
    .editor-area.active{display:flex}
    .editor-area textarea{width:100%;height:100%;font-family:'IBM Plex Mono',monospace;font-size:12px;line-height:1.6;padding:16px 20px;border:none;resize:none;outline:none;color:#333;background:#fff;tab-size:2}
    .preview-panel{display:flex;flex-direction:column;background:#E8E8E8;overflow:hidden}
    .preview-scroll{flex:1;overflow:auto;padding:24px;display:flex;flex-direction:column;align-items:center;gap:20px}
    .page-sheet{background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.12);position:relative;flex-shrink:0}
    .page-sheet iframe{border:none;display:block}
    .settings-bar{border-top:1px solid #E5E5E5;padding:20px 24px;display:flex;flex-wrap:wrap;gap:16px;align-items:end}
    .field{display:flex;flex-direction:column;gap:4px}
    .field label{font-size:10px;font-weight:500;letter-spacing:.05em;text-transform:uppercase;color:#666}
    .field select,.field input[type="text"],.field input[type="number"]{font-family:'IBM Plex Mono',monospace;font-size:12px;padding:6px 10px;border:1px solid #CCC;border-radius:2px;background:#fff;color:#333;transition:border-color .3s}
    .field select:focus,.field input:focus{outline:none;border-color:#000}
    .field select{min-width:100px}
    .field input[type="number"]{width:64px}
    .field input[type="text"]{min-width:140px}
    .margin-group{display:flex;gap:6px;align-items:end}
    .margin-group .field input[type="number"]{width:52px}
    .settings-divider{width:1px;height:32px;background:#E5E5E5;align-self:center}
    .actions-bar{border-top:1px solid #E5E5E5;padding:16px 24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .btn{font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:500;letter-spacing:.05em;padding:8px 20px;border:1px solid #333;border-radius:2px;cursor:pointer;transition:all .3s;text-transform:uppercase;background:transparent}
    .btn--primary{background:#333;color:#fff}
    .btn--primary:hover{background:#000;border-color:#000}
    .btn--primary:disabled{background:#CCC;border-color:#CCC;color:#fff;cursor:not-allowed}
    .btn--secondary{color:#333}
    .btn--secondary:hover{background:#333;color:#fff}
    .status-msg{font-size:11px;color:#999;margin-left:auto}
    .status-msg--ok{color:#2E7D32}
    .status-msg--err{color:#C62828}
    footer{padding-top:24px;border-top:1px solid #E5E5E5;font-size:11px;color:#999;letter-spacing:.05em;display:flex;justify-content:space-between}
    @media(max-width:800px){.sheet{padding:24px 12px}.titleblock h1{font-size:28px}.split-view{grid-template-columns:1fr;min-height:auto}.editor-panel{border-right:none;border-bottom:1px solid #E5E5E5;min-height:300px}.preview-panel{min-height:400px}.settings-bar{flex-direction:column;align-items:stretch}.actions-bar{flex-direction:column;align-items:stretch}.btn{text-align:center}.status-msg{margin-left:0}footer{flex-direction:column}}
  </style>
</head>
<body>
<div class="sheet"><div class="sheet__inner">
  <header class="runhead"><div class="runhead__brand">PageWright</div></header>
  <section class="titleblock"><h1>HTML → PDF</h1><p class="deck">Self-contained PHP document engine. Write HTML, configure pages, generate PDF — no dependencies.</p></section>
  <div class="ruler"></div>
  <div class="workspace"><div class="plate">
    <div class="plate__header">Document Editor</div>
    <div class="split-view">
      <div class="editor-panel">
        <div class="editor-tabs">
          <button class="editor-tab active" data-tab="html">HTML</button>
          <button class="editor-tab" data-tab="css">CSS</button>
        </div>
        <div class="editor-area active" data-area="html">
          <textarea id="htmlEditor" spellcheck="false"></textarea>
        </div>
        <div class="editor-area" data-area="css">
          <textarea id="cssEditor" spellcheck="false"></textarea>
        </div>
      </div>
      <div class="preview-panel">
        <div class="preview-scroll" id="previewScroll">
          <div style="color:#999;font-size:12px;padding:40px;">Enter HTML and click Generate PDF</div>
        </div>
      </div>
    </div>
    <div class="settings-bar">
      <div class="field"><label>Page size</label><select id="pageSize"><option value="A4">A4</option><option value="A3">A3</option><option value="A5">A5</option><option value="Letter">Letter</option><option value="Legal">Legal</option></select></div>
      <div class="field"><label>Orientation</label><select id="orientation"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></div>
      <div class="settings-divider"></div>
      <div class="field"><label>Unit</label><select id="marginUnit"><option value="mm">mm</option><option value="in">in</option><option value="pt">pt</option></select></div>
      <div class="field"><label>Margins</label><div class="margin-group">
        <div class="field"><label>T</label><input type="number" id="mT" value="20" min="0"></div>
        <div class="field"><label>R</label><input type="number" id="mR" value="15" min="0"></div>
        <div class="field"><label>B</label><input type="number" id="mB" value="20" min="0"></div>
        <div class="field"><label>L</label><input type="number" id="mL" value="15" min="0"></div>
      </div></div>
      <div class="settings-divider"></div>
      <div class="field"><label>Header</label><input type="text" id="headerText" placeholder="Optional"></div>
      <div class="field"><label>Footer</label><input type="text" id="footerText" placeholder="Page {{n}} of {{total}}"></div>
    </div>
    <div class="actions-bar">
      <button class="btn btn--primary" id="generateBtn">Generate PDF</button>
      <button class="btn btn--secondary" id="clearBtn">Clear</button>
      <span class="status-msg" id="statusMsg">Ready</span>
    </div>
  </div></div>
  <footer><span>© 2026 PageWright</span><span>Pure PHP · No dependencies</span></footer>
</div></div>

<script>
// Sample content
document.getElementById('htmlEditor').value = `<h1>Annual Report 2025</h1>

<h2>Executive Summary</h2>

<p>This report presents the key findings and strategic directions for the fiscal year 2025. Revenue grew 14% year-over-year, driven by expansion into the APAC region and the successful launch of three new product lines.</p>

<p>Operating margins improved from 18.2% to 21.7%, reflecting the operational efficiency program initiated in Q2. Headcount increased by 12% primarily in engineering and customer success functions.</p>

<h2>Financial Overview</h2>

<table>
  <thead>
    <tr><th>Quarter</th><th>Revenue</th><th>Expenses</th><th>Margin</th><th>Growth</th></tr>
  </thead>
  <tbody>
    <tr><td>Q1</td><td>$4.2M</td><td>$3.3M</td><td>21.4%</td><td>+12%</td></tr>
    <tr><td>Q2</td><td>$4.8M</td><td>$3.7M</td><td>22.9%</td><td>+14%</td></tr>
    <tr><td>Q3</td><td>$5.1M</td><td>$4.0M</td><td>21.6%</td><td>+6%</td></tr>
    <tr><td>Q4</td><td>$5.7M</td><td>$4.4M</td><td>22.8%</td><td>+12%</td></tr>
  </tbody>
  <tfoot>
    <tr><td>Total</td><td>$19.8M</td><td>$15.4M</td><td>22.2%</td><td>+14%</td></tr>
  </tfoot>
</table>

<h2>Key Initiatives</h2>

<ul>
  <li><strong>Market Expansion:</strong> Established regional offices in Tokyo, Singapore, and Sydney. APAC revenue contribution increased from 8% to 19%.</li>
  <li><strong>Product Development:</strong> Launched Analytics Pro, Compliance Suite, and the Mobile SDK. Combined new-product revenue: $2.1M.</li>
  <li><strong>Operational Efficiency:</strong> Migrated infrastructure to containerized deployments, reducing cloud spend by 23%.</li>
</ul>

<h2>Regional Performance</h2>

<table>
  <thead><tr><th>Region</th><th>Revenue</th><th>Share</th><th>YoY</th></tr></thead>
  <tbody>
    <tr><td>North America</td><td>$9.1M</td><td>46%</td><td>+8%</td></tr>
    <tr><td>Europe</td><td>$5.2M</td><td>26%</td><td>+11%</td></tr>
    <tr><td>APAC</td><td>$3.8M</td><td>19%</td><td>+62%</td></tr>
    <tr><td>Latin America</td><td>$1.7M</td><td>9%</td><td>+19%</td></tr>
  </tbody>
</table>

<h2>Strategic Outlook</h2>

<p>For 2026, we project revenue of $24-26M, representing 21-31% growth. The primary drivers will be continued APAC expansion, the general availability launch of Analytics Pro Enterprise, and deeper penetration into the regulated-industries segment via Compliance Suite.</p>

<h3>Risk Factors</h3>

<ol>
  <li>Currency exposure in APAC markets may compress reported revenue if USD strengthens.</li>
  <li>Compliance Suite requires regulatory certification in each jurisdiction; delays are possible.</li>
  <li>Competitive pressure from incumbents in the analytics segment may limit pricing power.</li>
</ol>

<p><em>This report was prepared by the Office of the CFO. All figures are subject to final auditor confirmation.</em></p>`;

document.getElementById('cssEditor').value = `body { font-size: 10pt; line-height: 1.5; }
h1 { font-size: 22pt; font-weight: bold; margin-top: 14pt; margin-bottom: 6pt; line-height: 1.2; }
h2 { font-size: 16pt; font-weight: bold; margin-top: 12pt; margin-bottom: 5pt; line-height: 1.3; }
h3 { font-size: 13pt; font-weight: bold; margin-top: 10pt; margin-bottom: 4pt; }
p  { font-size: 10pt; margin-bottom: 6pt; line-height: 1.5; }
table { font-size: 9pt; margin-bottom: 8pt; }
th { font-weight: bold; }
ul, ol { font-size: 10pt; margin-bottom: 6pt; margin-left: 18pt; line-height: 1.5; }
li { margin-bottom: 2pt; }`;

// Tab switching
document.querySelectorAll('.editor-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.editor-area').forEach(a => a.classList.remove('active'));
    tab.classList.add('active');
    document.querySelector(`.editor-area[data-area="${tab.dataset.tab}"]`).classList.add('active');
  });
});

// Tab key support
document.querySelectorAll('textarea').forEach(ta => {
  ta.addEventListener('keydown', e => {
    if (e.key === 'Tab') {
      e.preventDefault();
      const s = ta.selectionStart, end = ta.selectionEnd;
      ta.value = ta.value.substring(0, s) + '  ' + ta.value.substring(end);
      ta.selectionStart = ta.selectionEnd = s + 2;
    }
  });
});

// Generate PDF
const statusMsg = document.getElementById('statusMsg');
document.getElementById('generateBtn').addEventListener('click', () => {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '';

  const fields = {
    html: document.getElementById('htmlEditor').value,
    css:  document.getElementById('cssEditor').value,
    pageSize:    document.getElementById('pageSize').value,
    orientation: document.getElementById('orientation').value,
    marginTop:    document.getElementById('mT').value,
    marginRight:  document.getElementById('mR').value,
    marginBottom: document.getElementById('mB').value,
    marginLeft:   document.getElementById('mL').value,
    marginUnit:   document.getElementById('marginUnit').value,
    headerText:   document.getElementById('headerText').value,
    footerText:   document.getElementById('footerText').value,
  };

  Object.entries(fields).forEach(([k, v]) => {
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = k; input.value = v;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  statusMsg.textContent = 'Generating…';
  statusMsg.className = 'status-msg status-msg--ok';
  form.submit();
});

// Clear
document.getElementById('clearBtn').addEventListener('click', () => {
  document.getElementById('htmlEditor').value = '';
  document.getElementById('cssEditor').value = '';
  statusMsg.textContent = 'Ready';
  statusMsg.className = 'status-msg';
});
</script>
</body>
</html>