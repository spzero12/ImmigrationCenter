<?php

// Справочник объектов, на которых работают сотрудники: название, описание, адрес.

function getWorkSites(PDO $db): array
{
    return $db->query('SELECT * FROM work_sites ORDER BY name')->fetchAll();
}
