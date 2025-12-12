@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-3 mb-4">
    <h1 class="h3 text-primary fw-bold">{{ translate('Edit Order Logistic') }}</h1>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="POST" action="{{ route('order.logistics.update', [encrypt($logistic->invoice_no), $logistic->id]) }}
" enctype="multipart/form-data">
            @csrf

            <!-- LR Date and LR Number -->
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="lr_date" class="form-label fw-semibold">{{ translate('LR Date') }}</label>
                    <input type="date" id="lr_date" name="lr_date" class="form-control" value="{{ $logistic->lr_date }}" required>
                </div>
                <div class="col-md-6">
                    <label for="lr_no" class="form-label fw-semibold">{{ translate('LR Number') }}</label>
                    <input type="text" id="lr_no" name="lr_no" class="form-control" placeholder="Enter LR Number" value="{{ $logistic->lr_no }}" required>
                </div>
            </div>

            <!-- No. of Boxes and LR Amount -->
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label for="no_of_boxes" class="form-label fw-semibold">{{ translate('No. of Boxes') }}</label>
                    <input type="number" id="no_of_boxes" name="no_of_boxes" class="form-control" placeholder="Enter Number of Boxes" value="{{ $logistic->no_of_boxes }}" required>
                </div>
                <div class="col-md-6">
                    <label for="lr_amount" class="form-label fw-semibold">{{ translate('LR Amount') }}</label>
                    <input type="text" id="lr_amount" name="lr_amount" class="form-control" placeholder="Enter LR Amount" value="{{ $logistic->lr_amount }}" required>
                </div>
            </div>

            <!-- Multiple Image Upload -->
            <div class="mt-3">
                <label for="attachments" class="form-label fw-semibold">{{ translate('Attachments') }}</label>
                <div class="input-group">
                    <input type="file" id="attachments" name="attachments[]" class="form-control" accept=".jpeg,.jpg,.png" multiple>
                    <label class="input-group-text" for="attachments">{{ translate('Upload') }}</label>
                </div>

                <!-- Existing Images Preview -->
                <div class="mt-3 d-flex flex-wrap" id="file-preview">
                    @if($logistic->attachment)
                        @php
                            $attachments = explode(',', $logistic->attachment);
                        @endphp
                        @foreach($attachments as $index => $attachment)
                            <div class="position-relative m-2" style="width: 150px;">
                                <img src="{{ $attachment }}" class="img-thumbnail shadow-sm" style="max-width: 100%;">
                                <button type="button" class="btn btn-danger btn-sm position-absolute" style="top: 5px; right: 5px;" onclick="removeImage(this, '{{ $index }}')">&times;</button>
                            </div>
                        @endforeach
                    @else
                        <span class="text-muted">{{ translate('No attachments uploaded') }}</span>
                    @endif
                </div>
                <input type="hidden" name="remove_indexes" id="remove_indexes" value="">
            </div>

            <!-- Buttons -->
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success px-4">{{ translate('Save Changes') }}</button>
                <a href="{{ route('order.logistics') }}" class="btn btn-secondary px-4">{{ translate('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
    let removedIndexes = [];

    // Function to remove image and mark its index
    function removeImage(button, index) {
        button.parentElement.remove(); // Remove the image container
        removedIndexes.push(index); // Add index to removed list
        document.getElementById('remove_indexes').value = removedIndexes.join(',');

        // Clear file input when "Cut" is clicked
        document.getElementById('attachments').value = ''; // Reset file input
    }

    // Real-time preview for newly uploaded images
    document.getElementById('attachments').addEventListener('change', function () {
        let files = this.files;
        let previewContainer = document.getElementById('file-preview');

        // Display new file previews
        Array.from(files).forEach((file, index) => {
            if (file.type.startsWith('image')) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    let container = document.createElement('div');
                    container.className = 'position-relative m-2';
                    container.style.width = '150px';

                    container.innerHTML = `
                        <img src="${e.target.result}" class="img-thumbnail shadow-sm" style="max-width: 100%;">
                        <button type="button" class="btn btn-danger btn-sm position-absolute" style="top: 5px; right: 5px;" onclick="removeNewImage(this)">&times;</button>
                    `;
                    previewContainer.appendChild(container);
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Function to remove newly added images
    function removeNewImage(button) {
        button.parentElement.remove();
        // Clear file input when "Cut" is clicked
        document.getElementById('attachments').value = ''; // Reset file input
    }
</script>
@endsection
