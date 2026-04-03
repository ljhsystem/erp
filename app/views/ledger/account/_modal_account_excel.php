<!-- 경로: PROJECT_ROOT . '/app/views/ledger/account/_modal_account_excel.php' -->

<style>
.excel-modal-btn-group > button{
    white-space:nowrap;
}

.excel-modal-btn-group{
    gap:10px;
}

#excelUpload{
    min-width:210px;
}
</style>


<div class="modal fade"
     id="accountExcelModal"
     tabindex="-1"
     aria-labelledby="accountExcelModalLabel"
     aria-hidden="true">

  <div class="modal-dialog">

    <div class="modal-content">

      <form id="account-excel-upload-form"
            enctype="multipart/form-data">

        <div class="modal-header">

          <h5 class="modal-title"
              id="accountExcelModalLabel">
              계정과목 엑셀관리
          </h5>

          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"></button>

        </div>


        <div class="modal-body">

          <div class="d-flex align-items-center excel-modal-btn-group mb-3">

            <!-- 양식다운로드 -->
            <button type="button"
                    class="btn btn-outline-secondary btn-sm"
                    id="btnDownloadAccountTemplate">

                    양식 다운로드

            </button>


            <!-- 전체 계정 다운로드 -->
            <button type="button"
                    class="btn btn-outline-primary btn-sm"
                    id="btnDownloadAllAccounts">

                    계정 전체 다운로드

            </button>


            <!-- 파일 업로드 -->
            <input type="file"
                   name="excel"
                   id="excelUpload"
                   class="form-control form-control-sm"
                   accept=".xlsx,.xls">

          </div>


          <small class="form-text text-danger mb-3">

            계정과목을 대량 등록하거나 수정하려면  
            엑셀 파일을 업로드하세요.

          </small>


          <!-- 업로드 로딩 -->
          <div id="excelUploadSpinner"
               class="text-center mt-2"
               style="display:none;">

            <div class="spinner-border text-primary">

              <span class="visually-hidden">
              업로드 중...
              </span>

            </div>

            <div class="mt-1"
                 style="font-size:.9em;">

              업로드 중입니다...

            </div>

          </div>

        </div>


        <div class="modal-footer">

          <button type="submit"
                  class="btn btn-success btn-sm">

                  업로드

          </button>

          <button type="button"
                  class="btn btn-secondary btn-sm"
                  data-bs-dismiss="modal">

                  닫기

          </button>

        </div>

      </form>

    </div>

  </div>

</div>