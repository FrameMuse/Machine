<?php

class useful
{
    public function date_to_words(string $date, $lang = null, $letters = null) {
        $date = date_parse_from_format("Y-m-d", $date);
        $months = array(
            "1" => "Января",
            "2" => "Февраля",
            "3" => "Марта",
            "4" => "Апреля",
            "5" => "Мая",
            "6" => "Июня",
            "7" => "Июля",
            "8" => "Августа",
            "9" => "Сентября",
            "10" => "Октября",
            "11" => "Ноября",
            "12" => "Декабря",
        );
        
        return [
            "day" => $date["day"],
            "month" => $months[$date["month"]],
            "year" => $date["year"],
        ];
    }
}

?>