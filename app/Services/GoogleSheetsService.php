<?php
    namespace App\Services;

    use Google_Client;
    use Google_Service_Sheets;

    class GoogleSheetsService
    {
        protected $client;
        protected $service;

        public function __construct()
        {
            // Initialize the Google Client
            $this->client = new Google_Client();

            // Set the application name (optional)
            $this->client->setApplicationName(config('sheets.application_name', 'My Laravel App'));

            // Point to the credentials.json file in storage/app
            $this->client->setAuthConfig(storage_path('app/credentials.json'));

            // Set the necessary scopes for Google Sheets API
            $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS, Google_Service_Sheets::DRIVE]);

            // Initialize the Google Sheets service
            $this->service = new Google_Service_Sheets($this->client);
        }

        public function clearData($range)
        {
            $spreadsheetId = config('sheets.spreadsheet_id');            
            $this->service->spreadsheets_values->clear($spreadsheetId, $range, new \Google_Service_Sheets_ClearValuesRequest());
        }

        public function appendData($range, $values)
        {
            // Get the spreadsheet ID from the config file or .env
            $spreadsheetId = config('sheets.spreadsheet_id');            
            if (empty($spreadsheetId)) {
                throw new \Exception('Spreadsheet ID is missing.');
            }
            if (empty($range)) {
                throw new \Exception('Range is missing.');
            }
            // Prepare the data to append
            $body = new \Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            $params = [
                'valueInputOption' => 'RAW'
            ];
            // Append data to the Google Sheets
            return $this->service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        }

        public function getData($range)
        {
            $spreadsheetId = config('sheets.spreadsheet_id');
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            return $response->getValues();
        }
    }

