<!-- resources/views/product.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Product Page</title>
</head>
<body>
    <h1>Product Page</h1>
    <form id="generatePdfForm" action="{{ route('generatePdf') }}" method="POST">
        @csrf
        <input type="hidden" name="data" value="Your product data here 123">
        <button type="submit">Generate PDF</button>
    </form>

    <div id="pdfLink" style="display: none;">
        <a id="downloadPdfLink" href="#">Download PDF</a>
    </div>

    <script>
        document.getElementById('generatePdfForm').addEventListener('submit', function(event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            fetch(form.action, {
                method: form.method,
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.filename) {
                    checkPdfAvailability(data.filename);
                }
            })
            .catch(error => console.error('Error:', error));
        });

        function checkPdfAvailability(filename) {
            fetch(`/pdf-status/${filename}`)
                .then(response => response.json())
                .then(data => {
                    if (data.ready) {
                        // alert('Hello');
                        window.location.href = `/pdf/${filename}`;
                    } else {
                        setTimeout(() => checkPdfAvailability(filename), 2000);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
