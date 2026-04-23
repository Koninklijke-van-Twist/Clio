<section class="card upload-card">
    <h2><?= h(LOC('upload.title')) ?></h2>
    <p class="muted"><?= h(LOC('upload.description')) ?></p>

    <form method="post" enctype="multipart/form-data" class="upload-form" id="uploadForm">
        <input type="hidden" name="action" value="upload_transcript">
        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

        <label class="dropzone" id="dropzone" for="transcript_file">
            <strong id="dropzoneTitle"><?= h(LOC('upload.dropzone')) ?></strong>
            <span id="dropzoneHint"><?= h(LOC('upload.dropzone_hint')) ?></span>
            <span><?= h(LOC('upload.processing_notice')) ?></span>
            <span id="selectedFile" class="selected-file"></span>
            <input type="file" name="transcript_file" id="transcript_file" accept=".txt,.docx" required>
            <button type="button" id="chooseButton"
                class="button-secondary"><?= h(LOC('upload.select_button')) ?></button>
        </label>

        <div class="warning-box">
            <p><?= h(LOC('upload.warning')) ?></p>
            <label class="checkbox-row">
                <input type="checkbox" name="confirm_companywide" value="1" required>
                <span><?= h(LOC('upload.confirm_label')) ?></span>
            </label>
        </div>

        <button type="submit" class="button-primary"><?= h(LOC('upload.submit')) ?></button>
    </form>
</section>

<script>
    (function ()
    {
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('transcript_file');
        const chooseButton = document.getElementById('chooseButton');
        const selectedFile = document.getElementById('selectedFile');
        const dropzoneTitle = document.getElementById('dropzoneTitle');

        const initialTitle = <?= json_encode(LOC('upload.dropzone')) ?>;
        const dragTitle = <?= json_encode(LOC('upload.drag_active')) ?>;
        const selectedTemplate = <?= json_encode(LOC('upload.file_selected', '%s')) ?>;

        function setSelectedFile (fileName)
        {
            if (!fileName)
            {
                selectedFile.textContent = '';
                return;
            }

            selectedFile.textContent = selectedTemplate.replace('%s', fileName);
        }

        chooseButton.addEventListener('click', function ()
        {
            fileInput.click();
        });

        fileInput.addEventListener('change', function ()
        {
            const file = fileInput.files && fileInput.files.length ? fileInput.files[0] : null;
            setSelectedFile(file ? file.name : '');
        });

        ['dragenter', 'dragover'].forEach(function (eventName)
        {
            dropzone.addEventListener(eventName, function (event)
            {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('is-dragover');
                dropzoneTitle.textContent = dragTitle;
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName)
        {
            dropzone.addEventListener(eventName, function (event)
            {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('is-dragover');
                dropzoneTitle.textContent = initialTitle;
            });
        });

        dropzone.addEventListener('drop', function (event)
        {
            const files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
            if (!files || files.length === 0)
            {
                return;
            }

            fileInput.files = files;
            setSelectedFile(files[0].name);
        });
    })();
</script>