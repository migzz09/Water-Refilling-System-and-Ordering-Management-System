<?php
/**
 * NCR Cities and Barangays API
 * Returns list of NCR cities and their barangays
 * Method: GET
 */
header('Content-Type: application/json');

// NCR cities and barangays data
$ncr_cities = [
    'Taguig' => [
        'Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Central Signal Village', 'Fort Bonifacio',
        'Hagonoy', 'Ibayo-Tipas', 'Katuparan', 'Ligid-Tipas', 'Lower Bicutan', 'Maharlika Village',
        'Napindan', 'New Lower Bicutan', 'North Daang Hari', 'North Signal Village', 'Palingon',
        'Pinagsama', 'San Miguel', 'Santa Ana', 'South Daang Hari', 'South Signal Village', 'Tanyag',
        'Tuktukan', 'Upper Bicutan', 'Ususan', 'Wawa', 'Western Bicutan', 'Comembo', 'Cembo',
        'South Cembo', 'East Rembo', 'West Rembo', 'Pembo', 'Pitogo', 'Post Proper Northside',
        'Post Proper Southside', 'Rizal'
    ],
    'Quezon City' => ['Bagong Pag-asa', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'],
    'Manila' => ['Tondo', 'Binondo', 'Ermita', 'Malate', 'Paco'],
    'Makati' => ['Bangkal', 'Bel-Air', 'Magallanes', 'Pio del Pilar', 'San Lorenzo'],
    'Pasig' => ['Bagong Ilog', 'Oranbo', 'San Antonio', 'Santa Lucia', 'Ugong'],
    'Pateros' => ['Aguho', 'Martyrs', 'San Roque', 'Santa Ana']
];

try {
    echo json_encode([
        'success' => true,
        'data' => $ncr_cities,
        'message' => 'NCR cities and barangays retrieved successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving cities data: ' . $e->getMessage()
    ]);
}
