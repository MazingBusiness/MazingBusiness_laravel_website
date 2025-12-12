<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AttributesImport implements ToCollection, WithHeadingRow
{
    /**
     * Handle the collection of rows from the Excel file.
     *
     * @param Collection $rows
     * @throws ValidationException
     */
    public function collection(Collection $rows)
    {
        $errors = []; // Array to store errors

        foreach ($rows as $row) {
            // Skip if part_no is missing
            //if (empty($row['part_no'])) {
            //    $errors[] = "Part number is missing.";
            //    continue;
            //}

            $partNo = $row['part_no'];

            // Check and fetch group ID from category_groups table
            $groupId = null;
            if (!empty($row['group'])) {
                $group = DB::table('category_groups')->where('name', $row['group'])->first();
                if ($group) {
                    $groupId = $group->id;
                } else {
                    $errors[] = "Group '{$row['group']}' not found in category_groups table for part_no: $partNo.";
                }
            }

            // Check and fetch category ID from categories table
            $categoryId = null;
            if (!empty($row['category'])) {
                $category = DB::table('categories')->where('name', $row['category'])->first();
                if ($category) {
                    $categoryId = $category->id;
                } else {
                    $errors[] = "Category '{$row['category']}' not found in categories table for part_no: $partNo.";
                }
            }

            $attributes = [];
            $variations = [];
            $isVariantProduct = false; // Flag to check if the product has variations
            $variationParentPartNo = null; // Initialize the variation parent part number

            // Iterate over the attribute-value pairs in the row
            foreach ($row as $key => $value) {
                if (str_contains($key, 'attribute') && !empty($value)) {
                    // Correctly format the corresponding variant key
                    $variantKey = str_replace(' ', '_', $key) . '_varient'; // Ensures proper formatting like `attribute_1_varient`

                    // Get the attribute name and variant value
                    $attributeName = $value;           // e.g., Attribute 1
                    $attributeVariant = $row[$variantKey] ?? 0; // Value from Attribute X Varient, defaults to 0

                    // Ensure the value key exists
                    $valueKey = 'value_' . substr($key, -1); // Correct corresponding value key
                    if (!empty($row[$valueKey])) {
                        $attributeValue = $row[$valueKey]; // e.g., Value 1

                        // Check if the attribute exists in the 'attributes' table
                        $attribute = DB::table('attributes')->where('name', $attributeName)->first();

                        if (!$attribute) {
                            // If attribute does not exist, insert a new record
                            $attributeId = DB::table('attributes')->insertGetId([
                                'name' => $attributeName,
                                'type' => 'data', // Default type
                                'is_variation' => $attributeVariant, // Set from Attribute X Varient
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            // If attribute exists, update the required fields
                            DB::table('attributes')->where('id', $attribute->id)->update([
                                'name' => $attributeName,
                                'type' => 'data',
                                'is_variation' => $attributeVariant, // Update from Attribute X Varient
                                'updated_at' => now(),
                            ]);
                            $attributeId = $attribute->id;
                        }

                        // Check if the attribute value already exists
                        $existingValue = DB::table('attribute_values')
                            ->where('attribute_id', $attributeId)
                            ->where('value', $attributeValue)
                            ->first();

                        // If the value does not exist, insert it and get the ID
                        if (!$existingValue) {
                            $attributeValueId = DB::table('attribute_values')->insertGetId([
                                'attribute_id' => $attributeId,
                                'value' => $attributeValue,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            $attributeValueId = $existingValue->id;
                        }

                        // Add attribute_value_id to the attributes array
                        $attributes[] = $attributeValueId;

                        // If it's a variation and Attribute X Varient is 1, add to the variations array
                        if ($row['variation'] == 1 && $attributeVariant == 1) {
                            $variations[] = $attributeValueId;
                            $isVariantProduct = true; // Mark as a variant product

                            // Set the variation parent part number
                            $variationParentPartNo = $row['variation_parent_part_no'] ?? $partNo;
                        }
                    }
                }
            }

            // Update the products table with attributes, variations, group_id, and category_id
            DB::table('products')
                ->where('part_no', $partNo)
                ->update([
                    'attributes' => json_encode($attributes),
                    'variations' => json_encode($variations), // Update variations column
                    'variant_product' => $isVariantProduct ? 1 : 0, // Update variant_product column
                    'variation_parent_part_no' => $variationParentPartNo, // Update variation parent part no
                    'group_id' => $groupId, // Update group ID
                    'category_id' => $categoryId, // Update category ID
                ]);
        }

        // If there are errors, throw an exception with all error messages
        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'errors' => $errors,
            ]);
        }
    }
}
