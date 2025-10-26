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
        'Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Central Signal Village',
        'Fort Bonifacio', 'Hagonoy', 'Ibayo-Tipas', 'Katuparan', 'Ligid-Tipas',
        'Lower Bicutan', 'Maharlika Village', 'Napindan', 'New Lower Bicutan',
        'North Daang Hari', 'North Signal Village', 'Palingon', 'Pinagsama',
        'San Miguel', 'Santa Ana', 'South Daang Hari', 'South Signal Village',
        'Tanyag', 'Tuktukan', 'Upper Bicutan', 'Ususan', 'Wawa', 'Western Bicutan',
        'Comembo', 'Cembo', 'South Cembo', 'East Rembo', 'West Rembo', 'Pembo',
        'Pitogo', 'Post Proper Northside', 'Post Proper Southside', 'Rizal'
    ],
    'Pateros' => [
        'Aguho', 'Magtanggol', 'Martires del 96', 'Poblacion', 'San Pedro', 
        'San Roque', 'Santa Ana', 'Santo Rosario-Kanluran', 'Santo Rosario-Silangan', 'Tabacalera'
    ],
    'Makati' => [
        'Bangkal', 'Bel-Air', 'Carmona', 'Cembo', 'Comembo', 'Dasmarinas', 
        'East Rembo', 'Forbes Park', 'Guadalupe Nuevo', 'Guadalupe Viejo', 
        'Kasilawan', 'La Paz', 'Magallanes', 'Olympia', 'Palanan', 'Pembo', 
        'Pinagkaisahan', 'Pio del Pilar', 'Pitogo', 'Poblacion', 'Post Proper Northside',
        'Post Proper Southside', 'Rizal', 'San Antonio', 'San Isidro', 'San Lorenzo', 
        'Santa Cruz', 'Singkamas', 'South Cembo', 'Tejeros', 'Urdaneta', 'Valenzuela', 
        'West Rembo', 'Bangkal'
    ],
    'Pasig' => [
        'Bagong Ilog', 'Bagong Katipunan', 'Bambang', 'Buting', 'Caniogan', 
        'Dela Paz', 'Kalawaan', 'Kapasigan', 'Kapitolyo', 'Malinao', 'Manggahan', 
        'Maybunga', 'Oranbo', 'Palatiw', 'Pinagbuhatan', 'Pineda', 'Rosario', 
        'Sagad', 'San Antonio', 'San Joaquin', 'San Jose', 'San Miguel', 
        'San Nicolas', 'Santa Cruz', 'Santa Lucia', 'Santa Rosa', 'Santo Tomas', 
        'Santolan', 'Sumilang', 'Ugong'
    ]
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
