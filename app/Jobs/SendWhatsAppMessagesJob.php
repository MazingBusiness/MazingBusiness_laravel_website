<?php

namespace App\Jobs;

use App\Services\WhatsAppWebService; // Assuming you have a service to handle WhatsApp API calls
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Exception;

class SendWhatsAppMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $groupId;

    /**
     * Create a new job instance.
     *
     * @param  string  $groupId
     * @return void
     */
    public function __construct($groupId)
    {
        $this->groupId = $groupId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $batchSize = 10; // Define the batch size

        do {
            DB::beginTransaction(); // Start transaction
            // Retrieve up to 10 pending messages from wa_sales_queue
            $query = DB::table('wa_sales_queue')
                ->where('status', 'pending')  // Only process 'pending' messages
                ->where('flag', 0) // ✅ Ensure only fresh records are picked
                ->orderBy('id')  // Ensure consistent ordering
                ->limit($batchSize)  // Limit the number of records to 10
                ->lockForUpdate(); // 🚀 Prevents duplicate picking by other jobs
                //->get();


            // 🔹 Apply group_id filter only if it is provided
            if (!empty($this->groupId)) {
                $query->where('group_id', $this->groupId);
            }
            $messages = $query->get(); // Execute the query

            // Check if there are any messages to process
            if ($messages->isEmpty()) {
                DB::rollBack();
                break; // Exit loop if no more pending messages
            }

            // Collect IDs of picked messages
            $ids = $messages->pluck('id')->toArray();

            // 🔹 Step 2: Mark these messages as "processing" to prevent duplicate picking
            DB::table('wa_sales_queue')
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now()
                ]);

            DB::commit(); // Commit transaction to apply changes

            foreach ($messages as $message) {
                try {
                    $getManageDetails = User::where('phone',$message->to_number)->first();
                    \Log::info('Start processing to send whatsApp msg to '.$getManageDetails->name, [
                        'status' => 'Start',
                        'party_code' =>  $getManageDetails->party_code
                    ]);
                    // Prepare WhatsApp message content from the message record
                    $templateData = json_decode($message->content, true);

                    // Send the WhatsApp message using your WhatsApp API service
                    $whatsAppWebService = new WhatsAppWebService();
                    $jsonResponse = $whatsAppWebService->sendTemplateMessage($message->to_number, $templateData);

                    // Extract response details from the WhatsApp API response
                    $messageId = $jsonResponse['messages'][0]['id'] ?? '';  // Message ID may be blank
                    $messageStatus = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';

                    // Determine the message status based on whether the message ID is blank
                    $status = $messageId ? 'sent' : 'failed';  // If messageId is blank, set status to 'not sent'

                    // Update the wa_sales_queue with the response details
                    DB::table('wa_sales_queue')
                        ->where('id', $message->id)  // Update by the primary key 'id'
                        ->update([
                            'status' => $status,  // Update status based on the presence of messageId
                            'response' => json_encode($jsonResponse),  // Store the API response
                            'msg_id' => $messageId,  // Store the message ID
                            'flag' => 1, // ✅ Mark flag = 1 so no other job picks it
                            'msg_status' => $messageStatus,  // Store the message status
                            'updated_at' => now()  // Update the timestamp
                        ]);
                    
                    \Log::info('End processing to send whatsApp msg to '.$getManageDetails->name, [
                        'status' => $status,
                        'party_code' =>  $getManageDetails->party_code
                    ]);

                } catch (Exception $e) {
                    // Log the error and continue with the next message
                    Log::error('Error sending WhatsApp message for ID ' . $message->id . ': ' . $e->getMessage());
                    continue;  // Continue to the next message even if an error occurs
                }
            }

            // Wait for 2 minutes before processing the next batch
            sleep(60);  // Sleep for 120 seconds (2 minutes)
            
        } while (!$messages->isEmpty());  // Keep processing until no more pending messages
    }
}
