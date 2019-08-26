<?php
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 8/7/2018
 * Time: 12:48 PM
 */
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */

use \REDCap;
use \Plugin;

class CreateDisplay {

    public function renderTable($id, $header, $data, $title=null)
    {
        $grid = "";

        //Render table
        $grid .= '<div>';

        $grid .= '<table class="table cell-border" id="' . $id . '">';
        if (!empty($title)) {
            $grid .= "<caption>" . $title . "</caption>";
        }
        $grid .= $this->renderHeaderRow($header);
        $grid .= $this->renderTableRows($data);
        $grid .= '</table>';

        $grid .= '</div><br><br>';

        return $grid;
    }

    public function renderPlainTable($id, $header, $data, $title=null)
    {
        $grid = "";

        // Render table without the row stripping and bordering
        $grid .= '<div>';

        $grid .= '<table class="display" id="' . $id . '">';
        if (!empty($title)) {
            $grid .= "<caption>" . $title . "</caption>";
        }
        $grid .= $this->renderHeaderRow($header);
        $grid .= $this->renderTableRows($data);
        $grid .= '</table>';

        $grid .= '</div><br><br>';

        return $grid;
    }

    private function renderHeaderRow($header)
    {
        $row = '<thead><tr>';
        $num_cols = count($header);

        // This is a rough estimate to get the whole table to fit within the boundaries of the page. This is mainly to support
        // the Allergic Reactions table that is too large for a page with 12 pt font size.
        if ($num_cols > 12) {
            $font_size = 7;
        } else {
            $font_size = 12;
        }

        foreach ($header as $col_key => $this_col) {
            //$row .= '<th class="th-sm" scope="col">' . $this_col;
            $row .= '<th scope="col" style="font-size:' . $font_size . 'px !important">' . $this_col;
            $row .= '<i class="fa float-right" aria-hidden="true"></i>';
            $row .= '</th>';
        }

        $row .= '</tr></thead>';

        return $row;
    }

    private function renderTableRows($data)
    {
        global $module;
        $rows = '<tbody>';

        foreach ($data as $row_key => $this_row) {
            $rows .= '<tr>';

            foreach($this_row as $rowKey => $rowValue) {
                $rows .= '<td>' . $rowValue . '</td>';
            }

            // End row
            $rows .= '</tr>';
        }

        $rows .= '</tbody>';

        return $rows;
    }

}
