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

class CreateDisplay {

    public function renderTable($selectedProj, $header, $data)
    {
        $grid = "";

        //Render table
        $grid .= '<div class="table">';

        if (!empty($data)) {
            $grid .= '<table class="table table-striped table-bordered dataTable" cellspacing="2" width="100%">';
            $grid .= $this->renderHeaderRow($header);
            $grid .= $this->renderTableRows($data, $selectedProj);
            $grid .= '</table><br>';
        }

        $grid .= '</div>';

        return $grid;
    }

    private function renderHeaderRow($header = array())
    {
        $row = '<thead><tr>';

        foreach ($header as $col_key => $this_col) {
            $row .= '<th class="th-sm">' . $this_col;
            $row .= '<i class="fa fa-sort float-right" aria-hidden="true"></i>';
            $row .= '</th>';
        }

        $row .= '</tr></thead>';
        return $row;
    }

    private function renderTableRows($data, $selectedProj)
    {
        $rows = '<tbody>';

        foreach ($data as $row_key => $this_row) {
            $rows .= '<tr>';

            foreach($this_row as $rowKey => $rowValue) {
                $rows .= '<td>' . $this->getLabel($selectedProj, $rowKey, $rowValue) . '</td>';
            }

            // End row
            $rows .= '</tr>';
        }

        $rows .= '</tbody>';

        return $rows;
    }

    private function getLabel($selectedProj, $field, $value)
    {
        global $module;

        if (empty($field)) {
            $module->emError("The variable list is undefined so cannot retrieve data dictionary options.");
        }

        $fieldInfo = $selectedProj->metadata[$field];
        $label = null;
        switch ($fieldInfo["element_type"]) {
            case "select":
            case "radio":
            case "yesno":

                $optionList = $fieldInfo["element_enum"];
                $options = explode('\n', $optionList);
                foreach ($options as $optionKey => $optionValue) {
                    $option = explode(',', $optionValue);
                    if (trim($option[0]) == $value) {
                        if (empty($label)) {
                            $label = trim($option[1]);
                        } else {
                            $label .= ', ' . trim($option[1]);
                        }
                    }
                }

                break;
            case "checkbox":

                $optionList = $fieldInfo["element_enum"];
                $options = explode('\n', $optionList);
                foreach ($options as $optionKey => $optionValue) {
                    $option = explode(',', $optionValue);
                    if ($value[trim($option[0])] == 1) {
                        if (empty($label)) {
                            $label = trim($option[1]);
                        } else {
                            $label .= ', ' . trim($option[1]);
                        }
                    }
                }
                break;
            default:
                $label = $value;
        }

        return $label;
    }

}
