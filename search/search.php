<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediaVault - Hybrid Search Engine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-dark text-white p-3">
            <h3 class="mb-0 fw-bold">MediaVault Search Engine</h3>
            <p class="text-white-50 mb-0 small">Choose a search option below to quickly find your files.</p>
        </div>
        <div class="card-body p-4">
            
            <ul class="nav nav-tabs mb-4" id="searchMethodTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold" id="abr-tab" data-bs-toggle="tab" data-bs-target="#abr-pane" type="button" role="tab">📁 Search by Properties (ABR)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold" id="tbr-tab" data-bs-toggle="tab" data-bs-target="#tbr-pane" type="button" role="tab">🔍 Search by Name/Tag (TBR)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold" id="cbr-tab" data-bs-toggle="tab" data-bs-target="#cbr-pane" type="button" role="tab">⚙️ Advanced Feature Search (CBR)</button>
                </li>
            </ul>

            <div class="tab-content bg-white" id="searchMethodTabsContent">
                
                <div class="tab-pane fade show active" id="abr-pane" role="tabpanel">
                    <div class="bg-light p-3 rounded mb-3">
                        <span class="text-primary fw-bold">Property Search:</span> Find files using simple filters like what type of file it is or how large it is.
                    </div>
                    <form action="search_results.php" method="POST">
                        <input type="hidden" name="search_engine_type" value="ABR">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">What type of file are you looking for?</label>
                                <select name="file_type" class="form-select form-select-lg">
                                    <option value="">-- Show All Formats --</option>
                                    <option value="PDF">📄 PDF Document</option>
                                    <option value="DOCX">📝 Word Document</option>
                                    <option value="MP3">🎵 MP3 Audio</option>
                                    <option value="WAV">🎼 WAV Audio</option>
                                    <option value="MP4">🎬 MP4 Video</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">File Size Estimate</label>
                                <select name="size_range" class="form-select form-select-lg">
                                    <option value="">-- Any Size (No Limit) --</option>
                                    <option value="small">Small Files (Under 5 MB)</option>
                                    <option value="medium">Medium Files (5 MB to 50 MB)</option>
                                    <option value="large">Large Files (Over 50 MB)</option>
                                </select>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-4">Search Files</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="tbr-pane" role="tabpanel">
                    <div class="bg-light p-3 rounded mb-3">
                        <span class="text-success fw-bold">Keyword Search:</span> Type any part of a file name or any descriptive topic tags you remember.
                    </div>
                    <form action="search_results.php" method="POST">
                        <input type="hidden" name="search_engine_type" value="TBR">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Type file name or keyword tag</label>
                            <input type="text" name="keyword" class="form-control form-control-lg" placeholder="e.g. tutorial, lecture, music, ocean..." required>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg px-4">Search Keywords</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="cbr-pane" role="tabpanel">
                    <div class="bg-light p-3 rounded mb-3">
                        <span class="text-warning text-dark fw-bold">Feature Search:</span> Pick a characteristic category from the dropdown menu suggestions to automatically fill the search box, or freely type your own target criteria value.
                    </div>
                    <form action="search_results.php" method="POST">
                        <input type="hidden" name="search_engine_type" value="CBR">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Select Media Detail Type</label>
                                <select class="form-select form-select-lg" id="featureSuggestionsDropdown" onchange="autoFillValue()">
                                    <option value="">-- Choose target metric --</option>
                                    <option value="1920x1080">🎬 Screen Resolution (e.g., 1920x1080)</option>
                                    <option value="16:9">📐 Aspect Ratio (e.g., 16:9)</option>
                                    <option value="#0000FF">🎨 Color Profile Hex (e.g., #0000FF)</option>
                                    <option value="440Hz">🎵 Sound Pitch (e.g., 440Hz)</option>
                                </select>
                            </div>

                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Enter any media quality value</label>
                                <input type="text" name="feature_value" id="customValueInputField" class="form-control form-control-lg" 
                                       placeholder="Type anything like '1920x1080', '16:9', '#0000FF', or '440Hz'..." required>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-warning text-dark btn-lg px-4 fw-semibold">Search Features</button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function autoFillValue() {
    var dropdown = document.getElementById("featureSuggestionsDropdown");
    var textInput = document.getElementById("customValueInputField");
    if(dropdown.value !== "") {
        textInput.value = dropdown.value;
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>