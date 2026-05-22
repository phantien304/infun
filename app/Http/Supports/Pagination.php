<?php

namespace App\Http\Supports;

class Pagination
{
    public $total = 0;
    public $pageIndex = 1;
    public $pageSize = 20;
    public $numLinks = 7;
    public $url = '';
    public $text = 'Showing {start} to {end} of {total} ({pages} Pages)';
    public $textFirst = '|&lt;';
    public $textLast = '&gt;|';
    public $textNext = '&gt;';
    public $textPrev = '&lt;';
    public $styleLinks = 'pagination';
    public $styleResults = 'results';

    public function render()
    {
        $total = $this->total;

        if ($this->pageIndex < 1) {
            $page = 1;
        } else {
            $page = $this->pageIndex;
        }

        if (!(int)$this->pageSize) {
            $limit = 10;
        } else {
            $limit = $this->pageSize;
        }

        $numLinks = $this->numLinks;
        $numPages = ceil($total / $limit);

        $output = '';
        if ($page > 1) {
            $output .= ' <li class="page-item"><a class="page-link" href="javascript:void(0)" data-action="' . str_replace('_page', 1, $this->url) . '">' . $this->textFirst . '</a></li> <li class="page-item"><a class="page-link" href="javascript:void(0)" data-action="' . str_replace('_page', $page - 1, $this->url) . '">' . $this->textPrev . '</a></li> ';
        }

        if ($numPages > 1) {
            if ($numPages <= $numLinks) {
                $start = 1;
                $end = $numPages;
            } else {
                $start = $page - floor($numLinks / 2);
                $end = $page + floor($numLinks / 2);

                if ($start < 1) {
                    $end += abs($start) + 1;
                    $start = 1;
                }

                if ($end > $numPages) {
                    $start -= ($end - $numPages);
                    $end = $numPages;
                }
            }

            if ($start > 1) {
                //$output .= ' .... ';
            }

            for ($i = $start; $i <= $end; $i++) {
                if ($page == $i) {
                    $output .= ' <li class="page-item active"> <a class="page-link" href="javascript:void(0)">' . $i . '<span class="sr-only">(current)</span></a></li> ';
                } else {
                    $output .= ' <li class="page-item"><a class="page-link" href="javascript:void(0)" data-action="' . str_replace('_page', $i, $this->url) . '">' . $i . '</a></li> ';
                }
            }

            if ($end < $numPages) {
                //$output .= ' .... ';
            }
        }

        if ($page < $numPages) {
            $output .= ' <li class="page-item"><a class="page-link" href="javascript:void(0)" data-action="' . str_replace('_page', $page + 1, $this->url) . '">' . $this->textNext . '</a></li> <li class="page-item"><a class="page-link" href="javascript:void(0)" data-action="' . str_replace('_page', $numPages, $this->url) . '">' . $this->textLast . '</a></li> ';
        }

        $find = array(
            '{start}',
            '{end}',
            '{total}',
            '{pages}'
        );

        $replace = array(
            ($total) ? (($page - 1) * $limit) + 1 : 0,
            ((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit),
            $total,
            $numPages
        );
        return ($output ? '<ul class="' . $this->styleLinks . '">' . $output . '</ul>' : '') . '<div class="' . $this->styleResults . ' mt-2 mb-2">' . str_replace($find, $replace, $this->text) . '</div>';
    }
}
