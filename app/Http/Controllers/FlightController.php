<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
class FlightController extends Controller
{
    private const OPENSKY_API = 'https://opensky-network.org/api/states/all';
    private const DEMO_API = 'https://deskplan.lv/flight/all.json';
    public function getFlights(): JsonResponse
    {
        try {
            $response = Http::timeout(15)->get(self::OPENSKY_API);
            if ($response->successful()) {
                $data = $response->json();
                return response()->json(['time' => $data['time'] ?? null,'flights' => $this->transformFlights($data['states'] ?? []),'source' => 'opensky','count' => count($this->transformFlights($data['states'] ?? []))]);
            }
            $response = Http::timeout(10)->get(self::DEMO_API);
            if ($response->successful()) {
                $data = $response->json();
                return response()->json(['time' => $data['time'] ?? time(),'flights' => $this->transformFlights($data['states'] ?? []),'source' => 'demo','count' => count($this->transformFlights($data['states'] ?? []))]);
            }
            return response()->json(['error' => 'Failed to fetch flight data from both APIs','flights' => [],'count' => 0], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage(),'flights' => [],'count' => 0], 200);
        }
    }
    private function transformFlights(array $states): array
    {
        $flights = [];
        foreach ($states as $state) {
            if ($state[8] || !$state[5] || !$state[6]) {
                continue;
            }
            $flights[] = ['icao24' => $state[0],'callsign' => trim($state[1]) ?: 'N/A','country' => $state[2],'time_position' => $state[3],'last_contact' => $state[4],'longitude' => $state[5],'latitude' => $state[6],'baro_altitude' => $state[7],'on_ground' => $state[8],'velocity' => $state[9],'heading' => $state[10],'vertical_rate' => $state[11],'sensors' => $state[12],'geo_altitude' => $state[13],'squawk' => $state[14],'spi' => $state[15],'position_source' => $state[16]];
        }
        return $flights;
    }
    public function index()
    {
        return view('flights.radar');
    }
}
