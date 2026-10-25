<?php

// Справочник регионов: name — "Москва", name_dative — "г. Москве" (для
// адресата документов: "УВМ ГУ МВД России по г. Москве").

function getRegions(PDO $db): array
{
    return $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
}
