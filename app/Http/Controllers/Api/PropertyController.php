<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Amenity;
use App\Models\Category;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyImage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PropertyController extends Controller
{


    public function index()
    {
        $property = Property::where('status', '=', 'accepted')->get();
        if (count($property) > 0) {
            return PropertyResource::collection($property);
        } else {
            return response()->json(['message' => 'No record found'], 200);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required | min:5 | max:255',
            'headline' => 'required | min:5 | max:255',
            'description' => 'required | min:10',
            'bedrooms' => 'required | integer | min:1',
            'bathrooms' => 'required | integer | min:1',
            'location' => 'required | min:5 | max:255',
            'night_rate' => 'required | integer',
            'category_id' => 'required',
            'sleeps' => 'required | min:1',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'message' => "All fields are mandatory",
                'error' => $validator->messages()
            ], 422);
        }

        $fullAddress = $request->location;
        $coordinates = $this->getCoordinatesFromNominatim($fullAddress);

        if (!$coordinates) {
            return response()->json([
                'message' => 'Unable to get coordinates for the provided address.',
            ], 422);
        }

        $property = Property::create([
            'name' => $request->name,
            'headline' => $request->headline,
            'description' => $request->description,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'location' => $request->location,
            'sleeps' => $request->sleeps,
            'night_rate' => $request->night_rate,
            'category_id' => $request->category_id,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'owner_id' => Auth::guard('sanctum')->user()->id,
        ]);

        return ApiResponse::sendResponse(200, 'Property added successfully', $property);
    }
    private function getCoordinatesFromNominatim($fullAddress)
    {
        $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($fullAddress) . "&format=json&limit=1";

        $options = [
            "http" => [
                "header" => "User-Agent: MyAppName/1.0 (email@example.com)"
            ]
        ];

        $context = stream_context_create($options);

        $response = file_get_contents($url, false, $context);

        if ($response !== false) {
            $json = json_decode($response, true);

            if (!empty($json) && isset($json[0])) {
                return [
                    'latitude' => $json[0]['lat'],
                    'longitude' => $json[0]['lon']
                ];
            }
        }

        return null;
    }
    public function storeAmenities(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'amenities' => 'required|array',
            'amenities.*' => 'exists:amenities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 200);
        }

        $property = Property::findOrFail($propertyId);
        $property->propertyAmenities()->attach($request->amenities);

        return response()->json(['message' => 'Amenities added successfully.'], 200);
    }
    public function updateAmenities(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'amenities' => 'required|array',
            'amenities.*' => 'exists:amenities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $property = Property::findOrFail($propertyId);

        $property->propertyAmenities()->detach();

        $property->propertyAmenities()->attach($request->amenities);

        $updatedAmenities = $property->propertyAmenities()->get();

        return ApiResponse::sendResponse(200, 'Amenities updated successfully', $updatedAmenities);
    }

    public function getAmenities()
    {
        $amenities = Amenity::get();
        return response()->json([
            'status' => 200,
            'message' => 'data returned successfully',
            'data' => $amenities
        ]);
    }

    public function storeImages(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:4189',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 400);
        }

        $property = Property::findOrFail($propertyId);

        foreach ($request->file('images') as $image) {
            $path = $image->store('property_images', 'public');

            PropertyImage::create([
                'property_id' => $property->id,
                'image_path' => $path,
            ]);
        }

        return response()->json(['message' => 'Images uploaded successfully.'], 201);
    }
    public function updateImages(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:4189',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $property = Property::findOrFail($propertyId);

        $property->propertyImages()->delete();

        foreach ($request->file('images') as $image) {
            $path = $image->store('property_images', 'public');

            PropertyImage::create([
                'property_id' => $property->id,
                'image_path' => $path,
            ]);
        }

        return response()->json(['message' => 'Images updated successfully.'], 200);
    }

    public function show($id)
    {
        $property = Property::with(['propertyImages', 'propertyAmenities'])->findOrFail($id);
        return new PropertyResource($property);
    }

    public function update(Request $request, Property $property)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required | min:5 | max:255',
            'headline' => 'required | min:5 | max:255',
            'description' => 'required | min:10',
            'bedrooms' => 'required | integer | min:1',
            'bathrooms' => 'required | integer | min:1',
            'location' => 'required | min:5 | max:255',
            'night_rate' => 'required | integer',
            'category_id' => 'required',
            'sleeps' => 'required | min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "All fields are mandatory",
                'error' => $validator->messages()
            ], 200);
        }

        $property->update([
            'name' => $request->name,
            'headline' => $request->headline,
            'description' => $request->description,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'location' => $request->location,
            'night_rate' => $request->night_rate,
            'category_id' => $request->category_id,
            'sleeps' => $request->sleeps,
        ]);

        return ApiResponse::sendResponse('200', 'Property updated successfully', $property);
    }
    public function destroy(Property $property)
    {
        $property->delete();
        return response()->json([
            "message" => "Property deleted successfully"
        ], 200);
    }
    public function search(Request $request)
    {
        $query = Property::with(['category', 'owner', 'booking'])
            ->where('status', '=', 'accepted');

        if ($request->has('location') && $request->input('location') !== null) {
            $query->where('location', '=', $request->input('location'));
        }

        if ($request->has('sleeps') && $request->input('sleeps') !== null) {
            $query->where('sleeps', '>=', $request->input('sleeps'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            if ($startDate->isPast() || $endDate->isPast()) {
                return response()->json([
                    'message' => 'The selected date range has passed. Please choose future dates.',
                    'data' => []
                ], 200);
            }

            if ($startDate->gt($endDate)) {
                return response()->json(['message' => 'The start date cannot be after the end date.'], 200);
            }

            $query->whereDoesntHave('booking', function ($bookingQuery) use ($startDate, $endDate) {
                $bookingQuery->where('status', '=', 'accepted')
                    ->where(function ($dateQuery) use ($startDate, $endDate) {
                        $dateQuery->where(function ($query) use ($startDate, $endDate) {
                            $query->where('start_date', '<=', $endDate)
                                ->where('end_date', '>=', $startDate);
                        });
                    });
            });
        } else {
            return response()->json(['message' => 'Please provide both start and end dates.'], 200);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            return response()->json(['message' => 'No properties found'], 200);
        }

        return response()->json([
            'status' => '200',
            'message' => 'Data returned successfully',
            'data' => PropertyResource::collection($properties),
        ]);
    }

    public function getSuggestions(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['error' => 'Query is required'], 400);
        }

        $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($query) . "&format=json&limit=5&accept-language=en";

        $options = [
            "http" => [
                "header" => "User-Agent: MyAppName/1.0 (email@example.com)"
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return response()->json(['error' => 'Error fetching suggestions'], 500);
        }

        $json = json_decode($response, true);

        if (!empty($json)) {
            $suggestions = [];
            foreach ($json as $result) {
                $suggestions[] = [
                    'display_name' => $result['display_name'],
                    'lat' => $result['lat'],
                    'lon' => $result['lon'],
                ];
            }
            return response()->json($suggestions);
        }

        return response()->json(['error' => 'No results found'], 404);
    }

    public function getpropertycategory($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }
        $property = $category->properties;
        return propertyResource::collection($property);
    }

    public function delete($id)
    {
        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $property->delete();

        return response()->json(['message' => 'Property deleted successfully']);
    }

    public function filter(Request $request)
    {
        $request->validate([
            'amenity' => 'required|array',
            'amenity.*' => 'integer|exists:amenities,id',
        ]);
        $amenityIds = $request->input('amenity');
        $properties = Property::whereHas('propertyAmenities', function ($query) use ($amenityIds) {
            $query->whereIn('id', $amenityIds);
        })->get();
        return response()->json(['data' => $properties], 200);
    }
    public function filterByCategory(Request $request)
    {
        $request->validate([
            'category' => 'required|exists:categories,id'
        ]);
        $categoryId = $request->input('category');
        $properties = Property::where('category_id', '=', $categoryId)->where('status', '=', 'accepted')->get();
        return response()->json([
            'status' => 200,
            'message' => 'Data returned successfully',
            'data' => PropertyResource::collection($properties)
        ], 200);
    }


}