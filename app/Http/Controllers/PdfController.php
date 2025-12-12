<?php
    namespace App\Http\Controllers;

    use Illuminate\Http\Request;
    use App\Jobs\GeneratePdfReportJob;
    use Illuminate\Support\Facades\Storage;
    use App\Models\User;
    use App\Models\PdfReport;
    use Illuminate\Support\Facades\Cookie;
    use App\Services\WhatsAppWebService;
    use Illuminate\Support\Facades\File;

    class PdfController extends Controller
    {
        public function __construct()
        {
            ini_set('pcre.backtrack_limit', '2000000');
        }

        public function generatePdfPage()
        {
            return view('frontend.generate_pdf.generatePdf');
        }

        public function generatePdfFileName(Request $request){
            try{
                $user= User::where('id', $request->user_id)->first();
                $client_name = $user->name;
				// Replace spaces with underscores and remove special characters
        		$client_name = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(str_replace(' ', '_', $client_name)));
				// Generate the filename
                $filename = strtolower(str_replace(' ','_',$client_name)).'_'.rand(100,999).'_'.date('d-m-Y').'.pdf';
                session(['pdfFileName' => $filename]);
                return response()->json([
                    'message' => 'PDF file name had generated.',
                    'filename' => session('pdfFileName'),
                    'status' => true
                    
                ]);
            } catch (\Exception $e) {
                // Handle exceptions if the request fails
                //dd($e->getMessage());
                return response()->json([
                    'message' => 'PDF file name had not generated.',
                    'filename' => $e->getMessage(),
                    'status' => false
                ]);
            }
        }

        public function generatePdf(Request $request)
        {
            try{
                $data = array();
                $user= User::where('id', $request->userId)->first();
                $client_name = $user->name;
                // $filename = strtolower(str_replace(' ','_',$client_name)).'_'.rand(100,999).'_'.date('d-m-Y').'.pdf';
                // session(['pdfFileName' => $filename]);
                // echo session('pdfFileName');
                $filename = session('pdfFileName');
                $group = "";
                $category = "";
                $brand = "";
                $search = "";
                $type = $request->type;
                foreach ($request->all() as $key => $value) {
                    if($key == "prod_name"){
                      $search = $value;
                    }
                    if($key == "cat_groups" && !empty($value)){
                        $string = '(' . $value . ')';
                        $group = " AND `group_id` IN $string ";
                    }
        
                    if($key == "categories" && !empty($value)){
                        $string = '(' . $value . ')';
                        $category = " AND `category_id` IN $string ";
                    }
        
                    if($key == "brands" && !empty($value)){
                        $string = '(' . $value . ')';
                        $brand = " AND `brand_id` IN $string ";
                    }
                }

                $data['user_id']=$request->userId;
                $data['search']=$search;
                $data['group']=$group;
                $data['category']=$category;
                $data['brand']=$brand;
                $data['type']=$type;
                $data['client_name']=$client_name;
                $data['inhouse']=$request->inhouse; // edited by dipak

                // Create a new entry in the pdf_reports table
                PdfReport::create([
                    'filename' => $filename,
                    'user_id' => $request->userId,
                    'status' => 'pending'
                ]);
                $whatsAppWebService = ""; // Ensure this is correctly instantiated                        
                GeneratePdfReportJob::dispatch($data, $filename, $whatsAppWebService);              
                return response()->json([
                    'message' => 'PDF generation in progress',
                    'filename' => $filename
                ]);
            }catch (\Mpdf\MpdfException $e) {
                return response()->json([
                    'message' => $e->getMessage()
                ]);
                // echo 'PDF generation error: ' . $e->getMessage();
            }
        }

        // In your PdfController
        public function pdfStatus(Request $request)
        {
            $fileName = $request->get('fileName');
            $pdfPath = 'public/pdfs/'. $fileName;
            
            // Storage::download('public/pdfs/' . $fileName);
            // $pdfPath = 'public/pdfs/' . $fileName;

            if (Storage::exists($pdfPath)) {
                return response()->json(['ready' => true]);
            } else {
                return response()->json(['ready' => false]);
            }
        }

        public function downloadPdf($fileName)
        {
            // Define the full path to the file
            $fileUrl = 'https://mazingbusiness.com/public/pdfs/' . $fileName;

            // Check if the file exists by making a HEAD request
            $headers = get_headers($fileUrl, 1);

            // Check if the status is 200 OK
            if (strpos($headers[0], '200') !== false) {                
                // Remove a specific session key
                session()->forget('pdfFileName');
                // Use headers to initiate download from the remote server                
                return response()->streamDownload(function () use ($fileUrl) {
                    echo file_get_contents($fileUrl);
                }, $fileName);
            } else {
                // If the file doesn't exist, return a 404 response
                abort(404, 'File not found');
            }
        }

        public function checkPdfStatus($filename)
        {
            // $pdfData = PdfReport::where('filename',$filename)->first();
            // if($pdfData->download_status == 0){
            //     $exists = Storage::exists("public/pdfs/{$filename}");
            //     return response()->json([
            //         'ready' => $exists
            //     ]);
            // }else{
            //     return response()->json([
            //         'ready' => 'downloaded'
            //     ]);
            // } 
            // $exists = Storage::exists("public/pdfs/{$filename}");
            // $exists = Storage::disk('public')->exists("pdfs/{$filename}");
            $exists = File::exists(public_path("pdfs/{$filename}"));

            return response()->json([
                'ready' => $exists,
                'url'   => $exists ? url("public/pdfs/{$filename}") : null,
            ]);
            return response()->json([
                'ready' => $exists
            ]);           
        }

        public function updateDownloadPdfStatus(Request $request){
            $pdfData = PdfReport::where('filename',$request->filename)->first();
            session()->forget('pdfFileName');
            if($pdfData != NULL){
                $pdfData->update([
                    'download_status' => 1
                ]);
            }            
        }
    }
?>