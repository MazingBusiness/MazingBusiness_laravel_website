<!-- Upload Reviews Modal -->
<div id="upload-reviews-modal" class="modal fade">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title h6">{{ translate('Bulk Upload Reviews') }}</h4>
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
      </div>
      <div class="modal-body text-center">
        <p class="mt-1">{{ translate('Upload Reviews Excel') }}</p>
        <form class="form-horizontal" action="{{ route('reviews.bulk-upload') }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          <div class="form-group row">
            <div class="col-12">
              <div class="custom-file">
                <label class="custom-file-label">
                  <input type="file" name="bulk_file" class="custom-file-input" required>
                  <span class="custom-file-name">{{ translate('Choose File') }}</span>
                </label>
              </div>
            </div>
          </div>
          <div class="form-group mb-0">
            <button type="submit" class="btn btn-info">{{ translate('Upload Reviews') }}</button>
            <button type="button" class="btn btn-link mt-2" data-dismiss="modal">{{ translate('Cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div><!-- /.modal -->
