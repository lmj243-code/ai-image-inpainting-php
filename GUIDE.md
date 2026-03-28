# Dataler.com AI Image Generation API Guide - Precise Editing & Mask-Based Inpainting

## I. Platform Introduction

**Dataler.com** is a professional third-party AI API proxy platform offering the following core advantages:

- **22% of official price**: Significantly reduces AI image generation costs
- **Full model support**: Connects to almost all mainstream AI image generation models
- **Dynamic load balancing**: Intelligent scheduling ensures stable and efficient API responses
- **Gemini API compatible**: Seamless migration for existing projects

API Endpoint: `https://dataler.com/v1beta/models/{model}:generateContent`

---

## II. Core Features

### 2.1 AI Reverse Prompt Generation

Automatically generates detailed AI image generation prompts by analyzing images, including:
- Subject analysis (person/object/scene)
- Composition and perspective
- Color scheme
- Lighting effects
- Art style
- Material texture
- Background environment
- Camera/lens effects

### 2.2 Reference Image Product Replacement (Prompt Replacement Mode)

**Workflow Overview**:
1. **Analyze reference product**: AI deeply analyzes product images, extracting appearance, material, color, and other features
2. **Prompt integration**: Intelligently merge product description with user-provided scene prompt
3. **Generate new image**: Use new prompt combined with reference image to generate final image

**Application Scenarios**:
- E-commerce product image replacement
- Maintain scene atmosphere while changing products
- Batch generate similar style product display images

### 2.3 Original Product to Reference Product (Dual Image Fusion Mode)

**Workflow Overview**:
1. **Reverse engineer scene image**: Extract complete scene, person appearance, emotion, clothing, lighting, and other information
2. **Reverse engineer product image**: Detailed analysis of product appearance, size, material, color, structural features
3. **Intelligent integration**: Merge product description into scene description, keeping person and scene unchanged
4. **Dual image reference generation**: Use new prompt + both original images as references to generate result

**Core Advantages**:
- 100% consistent person appearance
- Completely preserved person emotional state
- Unchanged scene lighting and atmosphere
- Only replace product appearance

### 2.4 Precise Editing - MASK-Based Inpainting Mode (button2 core logic)

This is the most precise image replacement technology, suitable for product replacement scenarios requiring pixel-level control.

#### Workflow

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Scene Image    │     │  New Product    │     │  User Desc      │
│  (Reference)    │     │  Image          │     │  (Optional)     │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         ▼                       │                       │
┌─────────────────┐              │                       │
│  AI Generates   │◄─────────────┴───────────────────────┘
│  MASK           │    (Based on user description or auto-detect)
│ WHITE=Replace   │
│ BLACK=Keep      │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Inpainting Generation Stage               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │  Original   │  │  MASK       │  │  New        │         │
│  │  Scene      │  │  (B&W)      │  │  Product    │         │
│  │  (Keep)     │  │             │  │  (Source)   │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
│         │              │              │                     │
│         └──────────────┼──────────────┘                     │
│                        ▼                                    │
│              ┌─────────────────┐                           │
│              │   AI Inpainting │                           │
│              │   Precise       │                           │
│              │   Replacement   │                           │
│              └─────────────────┘                           │
└─────────────────────────────────────────────────────────────┘
```

#### Detailed Steps

**Step 1: Image Preparation**
- Load scene image (reference) and new product image
- Intelligent compression (max side 1500px, maintaining quality while reducing transmission)
- Convert to Base64 format

**Step 2: AI Generates MASK**

Two strategies based on whether user provides target description:

**Strategy A - User Specifies Target**:
```
Prompt Example:
"Generate an image: Please carefully observe this image and create a precise black-and-white MASK for inpainting.

[TARGET AREA TO BE WHITE MASKED]
[User described target item]

Please paint ALL content described above (including their complete occupied areas) in pure white (#FFFFFF).
Paint ALL other content in the image (background, walls, floor, people, text, other unrelated items) in pure black (#000000).

Rules:
- The MASK must be the EXACT SAME dimensions as the original image
- WHITE (#FFFFFF) = the target area described above (to be replaced)
- BLACK (#000000) = everything else (to be kept)
- Cover the ENTIRE target area including all parts mentioned in the description
- Use smooth edges with a small margin (3-5 pixels) around the target
- Clean black and white only, NO gray, NO gradients
- Do NOT include shadows or reflections in the white area
- Output ONLY the mask image, no text."
```

**Strategy B - Auto-Detect Subject**:
```
Prompt Example:
"Generate an image: Look at this image carefully. Create a precise MASK image for inpainting.
The MASK must be the EXACT SAME dimensions as the original image.
Identify the MAIN PRODUCT/SUBJECT in the image and mask it.

Rules:
- Paint the MAIN PRODUCT/SUBJECT area in PURE WHITE (#FFFFFF)
- Paint EVERYTHING ELSE in PURE BLACK (#000000)
- Cover the product outline with a small margin (3-5 pixels)
- Use smooth edges, no jagged borders
- Clean black and white only, NO gray, NO gradients
- Do NOT include shadows in the white area
- Output ONLY the mask image, no text."
```

**Step 3: Reverse Engineer Product Appearance Features**

AI analyzes new product image and extracts:
- Overall shape and contour
- Size proportions
- Precise colors (Space Gray, Ivory White, Rose Gold, etc.)
- Material texture (Metal/Plastic/Wood/Glass/Fabric, Matte/Glossy/Brushed)
- Surface details (texture, pattern, reflective properties, logo position)
- Structural features (buttons, ports, handles, hinges, stitching)
- Product quantity and arrangement

**Step 4: Inpainting Precise Replacement**

Construct request containing four parts:
1. **Text Instructions**: Detailed replacement rules
2. **Original Scene**: Preserved as background
3. **MASK**: Black & white image, white areas will be replaced
4. **New Product Image**: Replacement source

**Key Instruction Template**:
```
"Generate an image: I am providing three images:
1. The FIRST image is the original photo (the scene/background to keep)
2. The SECOND image is a black-and-white MASK where WHITE areas indicate the region to replace
3. The THIRD image is the new product/object that should be placed into the white masked area

**[PRODUCT APPEARANCE REFERENCE - from the THIRD image]**
[Reverse engineered product appearance description]

**[CRITICAL - PRODUCT FIDELITY RULES]**
The product from the THIRD image must be reproduced with 100% visual fidelity:
- EXACT original shape, proportions, and aspect ratio — NO stretching, squishing, warping, or distortion
- EXACT original colors, materials, textures, surface details, logos, and text
- EXACT original structural features (buttons, handles, edges, curves, patterns)
- Scale the product uniformly to fit the masked area — maintain width-to-height ratio strictly
- If the masked area is a different shape than the product, fit the product within the area with appropriate background fill — do NOT deform the product to fill the mask
- The product in the result must look like an exact copy of the THIRD image, just placed into a new scene

Placement rules:
- Adjust ONLY the viewing angle slightly to match the scene perspective
- Match the scene lighting direction and color temperature on the product surface
- Add natural shadows consistent with the scene light source
- Blend edges seamlessly with the surrounding area
- Keep ALL black masked areas (background, people, environment) EXACTLY unchanged
- Preserve the exact resolution and aspect ratio of the original image"
```

---

## III. Technical Key Points Summary

### 3.1 MASK Generation Key Points
- **Pure Black & White**: No gray or gradients allowed
- **Smooth Edges**: 3-5 pixel transition margin
- **No Shadows**: White area contains only the product itself
- **Same Dimensions**: MASK must have exact same dimensions as original image

### 3.2 Product Fidelity Key Points
- **Shape Unchanged**: No stretching, compressing, or distortion
- **Proportion Maintained**: Aspect ratio strictly preserved
- **Material Restoration**: Color, texture, reflective properties 100% restored
- **Structure Complete**: All visible components must be preserved

### 3.3 Scene Fusion Key Points
- **Perspective Matching**: Adjust product angle to match scene perspective
- **Consistent Lighting**: Match scene light source direction and color temperature
- **Natural Shadows**: Add shadows consistent with light source
- **Edge Blending**: Seamlessly integrate with surrounding environment

---

## IV. Application Scenarios

1. **E-commerce Product Replacement**: Model holding product images, quickly replace different styles
2. **Scene Marketing Images**: Maintain beautiful scenes while changing displayed products
3. **Advertising Material Production**: Batch generate different scene displays for same product
4. **Product Iteration Display**: Show different colors/configurations of product from same angle
5. **Virtual Try-on/Trial**: Naturally integrate products into user scenes

---

## V. Best Practices

1. **Image Quality**: Use clear, evenly lit product images
2. **Precise Description**: More detailed user target description leads to more accurate MASK positioning
3. **Try Multiple Times**: AI generation has some randomness, try multiple times if not satisfied
4. **Size Matching**: Scene and product images should have similar resolutions
5. **Compression Strategy**: Appropriate compression of large images can improve API response speed

---

## VI. API Request Format

### Basic Request Structure

```json
{
  "contents": [
    {
      "role": "user",
      "parts": [
        {"text": "Prompt content"},
        {"inlineData": {"mimeType": "image/jpeg", "data": "base64 encoded image"}}
      ]
    }
  ],
  "generationConfig": {
    "responseModalities": ["TEXT", "IMAGE"],
    "temperature": 0.3,
    "maxOutputTokens": 2048,
    "imageConfig": {
      "aspectRatio": "1:1",
      "imageSize": "1K"
    }
  }
}
```

### Supported Models

- `gemini-3-pro-image-preview`: Professional image generation model
- `gemini-3.1-flash-image-preview`: Fast image generation model

---

*This document is based on Dataler.com API and Gemini image generation technology*
