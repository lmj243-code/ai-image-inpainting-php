<?php
/**
 * AI Image Precise Generation & Natural Language Image Editing API
 * Precise Image Editing with Mask-Based Inpainting Example
 *
 * Function: Use MASK-based inpainting technology to precisely replace
 *           specified products in scene images with new products
 * Application: E-commerce product replacement, advertising material
 *              production, scene marketing image generation
 *
 * Author: LT
 * Date: 2026-03-28
 */

class DatalerInpaintingAPI
{
    private string $apiKey;
    private string $baseUrl = 'https://dataler.com/v1beta/models/'; // Highly recommended API, super cheap and stable! 22% of official price!
    private string $model = 'gemini-3-pro-image-preview';
    private int $timeout = 300; // 5 minutes timeout

    // Log callback function
    public $logCallback = null;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Output log message
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] {$message}" . PHP_EOL;

        if ($this->logCallback && is_callable($this->logCallback)) {
            call_user_func($this->logCallback, $logLine);
        } else {
            echo $logLine;
        }
    }

    /**
     * Read image and convert to Base64
     */
    public function imageToBase64(string $imagePath): ?string
    {
        if (!file_exists($imagePath)) {
            $this->log("Error: Image does not exist: {$imagePath}");
            return null;
        }

        $binary = file_get_contents($imagePath);
        if ($binary === false) {
            $this->log("Error: Cannot read image: {$imagePath}");
            return null;
        }

        return base64_encode($binary);
    }

    /**
     * Compress image (maintain aspect ratio, limit max side length)
     *
     * @param string $imagePath Original image path
     * @param int $maxSide Maximum side length
     * @param int $quality JPEG quality (1-100)
     * @return array [base64, width, height] or null
     */
    public function compressImage(string $imagePath, int $maxSide = 1500, int $quality = 85): ?array
    {
        if (!extension_loaded('gd')) {
            $this->log("Warning: GD extension not loaded, using original image");
            $base64 = $this->imageToBase64($imagePath);
            return $base64 ? ['base64' => $base64, 'width' => 0, 'height' => 0] : null;
        }

        // Get image info
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            $this->log("Error: Cannot get image info");
            return null;
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        $this->log("Original size: {$origWidth}x{$origHeight}");

        // If image is already smaller than max side, return original
        if ($origWidth <= $maxSide && $origHeight <= $maxSide) {
            $this->log("Image size is appropriate, no compression needed");
            $base64 = $this->imageToBase64($imagePath);
            return $base64 ? ['base64' => $base64, 'width' => $origWidth, 'height' => $origHeight] : null;
        }

        // Calculate scale ratio
        $scale = $maxSide / max($origWidth, $origHeight);
        $newWidth = (int)($origWidth * $scale);
        $newHeight = (int)($origHeight * $scale);

        $this->log("Compress: {$origWidth}x{$origHeight} -> {$newWidth}x{$newHeight}");

        // Create source image
        switch ($mimeType) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($imagePath);
                break;
            case 'image/webp':
                $srcImage = imagecreatefromwebp($imagePath);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($imagePath);
                break;
            default:
                $this->log("Error: Unsupported image format: {$mimeType}");
                return null;
        }

        if (!$srcImage) {
            $this->log("Error: Cannot create source image");
            return null;
        }

        // Create destination image
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve PNG transparency
        if ($mimeType === 'image/png') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize image
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $origWidth, $origHeight
        );

        // Output to memory
        ob_start();
        imagejpeg($dstImage, null, $quality);
        $compressedData = ob_get_clean();

        // Free resources
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        if ($compressedData === false) {
            $this->log("Error: Compression failed");
            return null;
        }

        $this->log("Compressed Base64 length: " . strlen(base64_encode($compressedData)) . " chars");

        return [
            'base64' => base64_encode($compressedData),
            'width' => $newWidth,
            'height' => $newHeight
        ];
    }

    /**
     * Call Dataler API
     *
     * @param array $requestData Request data
     * @return array|null Response data or null
     */
    private function callAPI(array $requestData): ?array
    {
        $url = $this->baseUrl . $this->model . ':generateContent';

        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $this->log("Sending API request to: {$url}");

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("CURL Error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $this->log("HTTP Error: {$httpCode}");
            $this->log("Response: " . substr($response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON Parse Error: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Step 1: Generate MASK
     *
     * @param string $sceneImageBase64 Scene image Base64
     * @param string|null $targetDesc User description of target to replace (optional)
     * @return string|null MASK image Base64
     */
    public function generateMask(string $sceneImageBase64, ?string $targetDesc = null): ?string
    {
        $this->log("========================================");
        $this->log("[Step 2/4] AI generating target area MASK...");
        $this->log("========================================");

        // Build MASK generation prompt
        if ($targetDesc && strlen($targetDesc) > 0) {
            $this->log("Targeting based on user description: {$targetDesc}");
            $maskPrompt = "Generate an image: Please carefully observe this image and create a precise black-and-white MASK for inpainting.\n\n" .
                "[TARGET AREA TO BE WHITE MASKED]\n" .
                $targetDesc . "\n\n" .
                "Please paint ALL content described above (including their complete occupied areas) in pure white (#FFFFFF).\n" .
                "Paint ALL other content in the image (background, walls, floor, people, text, other unrelated items) in pure black (#000000).\n\n" .
                "Rules:\n" .
                "- The MASK must be the EXACT SAME dimensions as the original image\n" .
                "- WHITE (#FFFFFF) = the target area described above (to be replaced)\n" .
                "- BLACK (#000000) = everything else (to be kept)\n" .
                "- Cover the ENTIRE target area including all parts mentioned in the description\n" .
                "- Use smooth edges with a small margin (3-5 pixels) around the target\n" .
                "- Clean black and white only, NO gray, NO gradients\n" .
                "- Do NOT include shadows or reflections in the white area\n" .
                "Output ONLY the mask image, no text.";
        } else {
            $this->log("Will auto-detect main product in image");
            $maskPrompt = "Generate an image: Look at this image carefully. Create a precise MASK image for inpainting. " .
                "The MASK must be the EXACT SAME dimensions as the original image. " .
                "Identify the MAIN PRODUCT/SUBJECT in the image and mask it.\n" .
                "Rules:\n" .
                "- Paint the MAIN PRODUCT/SUBJECT area in PURE WHITE (#FFFFFF)\n" .
                "- Paint EVERYTHING ELSE in PURE BLACK (#000000)\n" .
                "- Cover the product outline with a small margin (3-5 pixels)\n" .
                "- Use smooth edges, no jagged borders\n" .
                "- Clean black and white only, NO gray, NO gradients\n" .
                "- Do NOT include shadows in the white area\n" .
                "Output ONLY the mask image, no text.";
        }

        $requestData = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $maskPrompt],
                        ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $sceneImageBase64]]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE']
            ]
        ];

        $this->log("Sending MASK generation request...");
        $response = $this->callAPI($requestData);

        if (!$response) {
            $this->log("Error: MASK generation API call failed");
            return null;
        }

        // Extract MASK image
        if (isset($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    $this->log("MASK generated successfully!");
                    return $part['inlineData']['data'];
                }
            }
        }

        $this->log("Error: Could not extract MASK image from response");
        if (isset($response['candidates'][0]['finishReason'])) {
            $this->log("Finish reason: " . $response['candidates'][0]['finishReason']);
        }

        return null;
    }

    /**
     * Step 2: Reverse engineer product appearance features
     *
     * @param string $productImageBase64 Product image Base64
     * @return string Product description
     */
    public function analyzeProduct(string $productImageBase64): string
    {
        $this->log("========================================");
        $this->log("[Step 3/4] Reverse engineering product appearance features...");
        $this->log("========================================");

        $productDescPrompt = "Please describe the visual appearance features of the product in this image in extreme detail, for precise reproduction in another image.\n\n" .
            "Must describe:\n" .
            "1. Overall shape and contour: precise shape, curves, angles\n" .
            "2. Size proportions: proportional relationships between parts (e.g., aspect ratio)\n" .
            "3. Colors: precise color of each part (use specific color names like 'Space Gray', 'Ivory White', 'Rose Gold')\n" .
            "4. Material texture: Metal/Plastic/Wood/Glass/Fabric, etc., Matte/Glossy/Brushed\n" .
            "5. Surface details: texture, pattern, reflective properties, logo position and style\n" .
            "6. Structural features: buttons, ports, handles, hinges, stitching and all visible components\n" .
            "7. Product quantity and arrangement: single or multiple, how arranged\n\n" .
            "Please output in English, in concise descriptive paragraph format, no numbering, directly describe appearance features.";

        $requestData = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $productDescPrompt],
                        ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $productImageBase64]]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT'],
                'temperature' => 0.2,
                'maxOutputTokens' => 1024
            ]
        ];

        $this->log("Sending product feature analysis request...");
        $response = $this->callAPI($requestData);

        $description = '';
        if ($response && isset($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $description = $part['text'];
                    break;
                }
            }
        }

        if (strlen($description) > 0) {
            $this->log("Product feature analysis successful! Description length: " . strlen($description) . " chars");
            $this->log("Preview: " . substr($description, 0, 150) . "...");
        } else {
            $this->log("Product feature analysis returned no result, will rely on image only for replacement");
        }

        return $description;
    }

    /**
     * Step 3: Execute Inpainting precise replacement
     *
     * @param string $sceneImageBase64 Scene image Base64
     * @param string $maskBase64 MASK Base64
     * @param string $productImageBase64 New product image Base64
     * @param string $productDescription Product description
     * @param string|null $targetDesc User specified replacement target description
     * @return string|null Generated image Base64
     */
    public function inpaint(
        string $sceneImageBase64,
        string $maskBase64,
        string $productImageBase64,
        string $productDescription,
        ?string $targetDesc = null
    ): ?string {
        $this->log("========================================");
        $this->log("[Step 4/4] Executing precise replacement (Inpainting)...");
        $this->log("========================================");

        // Build Inpainting instruction
        $inpaintPrompt = "Generate an image: I am providing three images:\n" .
            "1. The FIRST image is the original photo (the scene/background to keep)\n" .
            "2. The SECOND image is a black-and-white MASK where WHITE areas indicate the region to replace\n" .
            "3. The THIRD image is the new product/object that should be placed into the white masked area\n\n";

        // Add reverse engineered product appearance description
        if (strlen($productDescription) > 0) {
            $inpaintPrompt .= "**[PRODUCT APPEARANCE REFERENCE - from the THIRD image]**\n" .
                $productDescription . "\n\n";
        }

        // If user described replacement target, add context
        if ($targetDesc && strlen($targetDesc) > 0) {
            $inpaintPrompt .= "Context: The white masked area in the original image corresponds to: [{$targetDesc}]. " .
                "Replace this entire area with the product from the third image.\n\n";
        }

        $inpaintPrompt .= "**[CRITICAL - PRODUCT FIDELITY RULES]**\n" .
            "The product from the THIRD image must be reproduced with 100% visual fidelity:\n" .
            "- EXACT original shape, proportions, and aspect ratio — NO stretching, squishing, warping, or distortion\n" .
            "- EXACT original colors, materials, textures, surface details, logos, and text as described above\n" .
            "- EXACT original structural features (buttons, handles, edges, curves, patterns)\n" .
            "- Scale the product uniformly to fit the masked area — maintain width-to-height ratio strictly\n" .
            "- If the masked area is a different shape than the product, fit the product within the area with appropriate background fill — do NOT deform the product to fill the mask\n" .
            "- The product in the result must look like an exact copy of the THIRD image, just placed into a new scene\n\n" .
            "Placement rules:\n" .
            "- Adjust ONLY the viewing angle slightly to match the scene perspective\n" .
            "- Match the scene lighting direction and color temperature on the product surface\n" .
            "- Add natural shadows consistent with the scene light source\n" .
            "- Blend edges seamlessly with the surrounding area\n" .
            "- Keep ALL black masked areas (background, people, environment) EXACTLY unchanged\n" .
            "- Preserve the exact resolution and aspect ratio of the original image";

        $requestData = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $inpaintPrompt],
                        ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $sceneImageBase64]],
                        ['inlineData' => ['mimeType' => 'image/png', 'data' => $maskBase64]],
                        ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $productImageBase64]]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE']
            ]
        ];

        $this->log("Sending Inpainting request (Original + MASK + Product + Description)...");
        $response = $this->callAPI($requestData);

        if (!$response) {
            $this->log("Error: Inpainting API call failed");
            return null;
        }

        // Extract generated image
        if (isset($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    $this->log("========================================");
                    $this->log("[Precise editing successful!]");
                    $this->log("========================================");
                    return $part['inlineData']['data'];
                }
            }
        }

        $this->log("Error: Could not find generated image in response");
        if (isset($response['candidates'][0]['finishReason'])) {
            $this->log("Finish reason: " . $response['candidates'][0]['finishReason']);
        }

        return null;
    }

    /**
     * Complete mask replacement workflow
     *
     * @param string $sceneImagePath Scene image path (containing product to replace)
     * @param string $productImagePath New product image path
     * @param string|null $targetDesc User description of target to replace (e.g., "red handbag")
     * @param string $outputPath Output image path
     * @param bool $compress Whether to compress images
     * @return bool Success status
     */
    public function replaceProductWithMask(
        string $sceneImagePath,
        string $productImagePath,
        ?string $targetDesc = null,
        string $outputPath = 'output.png',
        bool $compress = true
    ): bool {
        $this->log("========================================");
        $this->log("[Precise Editing - MASK Mode] Starting execution");
        $this->log("========================================");
        $this->log("Scene (Reference): {$sceneImagePath}");
        $this->log("New Product: {$productImagePath}");
        if ($targetDesc) {
            $this->log("Replacement Target: {$targetDesc}");
        } else {
            $this->log("Replacement Target: Auto-detect subject");
        }

        // ========== Step 1: Read and prepare images ==========
        $this->log("========================================");
        $this->log("[Step 1/4] Reading and preparing images...");
        $this->log("========================================");

        if ($compress) {
            $sceneData = $this->compressImage($sceneImagePath, 1500);
            $productData = $this->compressImage($productImagePath, 1500);
        } else {
            $sceneBase64 = $this->imageToBase64($sceneImagePath);
            $productBase64 = $this->imageToBase64($productImagePath);
            $sceneData = $sceneBase64 ? ['base64' => $sceneBase64, 'width' => 0, 'height' => 0] : null;
            $productData = $productBase64 ? ['base64' => $productBase64, 'width' => 0, 'height' => 0] : null;
        }

        if (!$sceneData || !$productData) {
            $this->log("Error: Image reading failed");
            return false;
        }

        $sceneBase64 = $sceneData['base64'];
        $productBase64 = $productData['base64'];

        $this->log("Image preparation complete");

        // ========== Step 2: Generate MASK ==========
        $maskBase64 = $this->generateMask($sceneBase64, $targetDesc);
        if (!$maskBase64) {
            $this->log("Error: MASK generation failed");
            return false;
        }

        // Save MASK for debugging (optional)
        $maskDebugPath = sys_get_temp_dir() . '/mask_' . time() . '.png';
        file_put_contents($maskDebugPath, base64_decode($maskBase64));
        $this->log("MASK debug file: {$maskDebugPath}");

        // ========== Step 3: Reverse engineer product appearance ==========
        $productDescription = $this->analyzeProduct($productBase64);

        // ========== Step 4: Execute Inpainting ==========
        $resultBase64 = $this->inpaint(
            $sceneBase64,
            $maskBase64,
            $productBase64,
            $productDescription,
            $targetDesc
        );

        if (!$resultBase64) {
            $this->log("Error: Inpainting generation failed");
            return false;
        }

        // Save result
        $resultBinary = base64_decode($resultBase64);
        if (file_put_contents($outputPath, $resultBinary) === false) {
            $this->log("Error: Cannot save result image");
            return false;
        }

        $this->log("Result image saved to: {$outputPath}");
        $this->log("Precise editing workflow complete");

        return true;
    }
}

// ==================== Usage Examples ====================

/**
 * Example 1: Basic usage - Auto-detect and replace main product
 */
function example1_basic()
{
    echo "=== Example 1: Auto-detect and replace main product ===\n";

    $apiKey = 'your-api-key-here'; // Replace with your API Key
    $api = new DatalerInpaintingAPI($apiKey);

    $result = $api->replaceProductWithMask(
        'scene.jpg',        // Scene image: model holding old product
        'new_product.jpg',  // New product image: product to replace with
        null,               // No target specified, auto-detect
        'output_auto.png',  // Output path
        true                // Enable compression
    );

    echo $result ? "Success!\n" : "Failed!\n";
}

/**
 * Example 2: Advanced usage - Specify replacement target
 */
function example2_targeted()
{
    echo "=== Example 2: Specify replacement target ===\n";

    $apiKey = 'your-api-key-here';
    $api = new DatalerInpaintingAPI($apiKey);

    $result = $api->replaceProductWithMask(
        'model_with_bag.jpg',   // Scene image: model holding red handbag
        'blue_bag.jpg',         // New product image: blue handbag
        'red handbag',          // Explicitly specify to replace the red handbag
        'output_blue_bag.png',  // Output path
        true
    );

    echo $result ? "Success!\n" : "Failed!\n";
}

/**
 * Example 3: Step-by-step (more flexible control)
 */
function example3_step_by_step()
{
    echo "=== Example 3: Step-by-step ===\n";

    $apiKey = 'your-api-key-here';
    $api = new DatalerInpaintingAPI($apiKey);

    // 1. Prepare images
    $sceneData = $api->compressImage('scene.jpg', 1500);
    $productData = $api->compressImage('product.jpg', 1500);

    if (!$sceneData || !$productData) {
        echo "Image preparation failed\n";
        return;
    }

    // 2. Generate MASK
    $maskBase64 = $api->generateMask($sceneData['base64'], 'laptop on the table');
    if (!$maskBase64) {
        echo "MASK generation failed\n";
        return;
    }

    // 3. Analyze product
    $productDesc = $api->analyzeProduct($productData['base64']);

    // 4. Execute replacement
    $resultBase64 = $api->inpaint(
        $sceneData['base64'],
        $maskBase64,
        $productData['base64'],
        $productDesc,
        'laptop on the table'
    );

    if ($resultBase64) {
        file_put_contents('output_step.png', base64_decode($resultBase64));
        echo "Success! Result saved to output_step.png\n";
    } else {
        echo "Replacement failed\n";
    }
}

/**
 * Example 4: Batch processing
 */
function example4_batch()
{
    echo "=== Example 4: Batch processing ===\n";

    $apiKey = 'your-api-key-here';
    $api = new DatalerInpaintingAPI($apiKey);

    $scenes = [
        'scene1.jpg',
        'scene2.jpg',
        'scene3.jpg'
    ];

    $newProduct = 'new_product.jpg';
    $target = 'phone in the center of the image';

    foreach ($scenes as $index => $scene) {
        echo "Processing " . ($index + 1) . "/" . count($scenes) . "...\n";

        $output = "output_batch_{$index}.png";
        $success = $api->replaceProductWithMask(
            $scene,
            $newProduct,
            $target,
            $output,
            true
        );

        echo $success ? "✓ {$output}\n" : "✗ Failed\n";

        // Add delay to avoid API rate limiting
        if ($index < count($scenes) - 1) {
            sleep(2);
        }
    }
}

/**
 * Example 5: Custom logging callback
 */
function example5_custom_logging()
{
    echo "=== Example 5: Custom logging callback ===\n";

    $apiKey = 'your-api-key-here';
    $api = new DatalerInpaintingAPI($apiKey);

    // Custom log handler: write to file
    $logFile = fopen('inpainting.log', 'a');
    $api->logCallback = function($message) use ($logFile) {
        fwrite($logFile, $message);
        echo $message; // Also output to screen
    };

    $result = $api->replaceProductWithMask(
        'scene.jpg',
        'product.jpg',
        null,
        'output.png',
        true
    );

    fclose($logFile);
    echo $result ? "Success!\n" : "Failed!\n";
}

// ==================== Command Line Execution ====================

if (PHP_SAPI === 'cli') {
    echo "AI Image Generation API - Precise Editing Example\n";
    echo "========================================\n";
    echo "\nAvailable examples:\n";
    echo "1 - Basic usage (auto-detect)\n";
    echo "2 - Specify replacement target\n";
    echo "3 - Step-by-step\n";
    echo "4 - Batch processing\n";
    echo "5 - Custom logging\n";
    echo "\nUsage: php {$argv[0]} [example_number]\n";

    if (isset($argv[1])) {
        switch ($argv[1]) {
            case '1': example1_basic(); break;
            case '2': example2_targeted(); break;
            case '3': example3_step_by_step(); break;
            case '4': example4_batch(); break;
            case '5': example5_custom_logging(); break;
            default: echo "Invalid example number\n";
        }
    }
}
