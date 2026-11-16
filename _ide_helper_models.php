<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $link
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppNotification whereUserId($value)
 */
	class AppNotification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $subtotal
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CartItem whereUserId($value)
 */
	class CartItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $image
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category roots()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_session_id
 * @property string $status
 * @property int|null $escalated_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $escalatedTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatMessage> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereEscalatedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereGuestSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereUserId($value)
 */
	class ChatConversation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $chat_conversation_id
 * @property string $role
 * @property string $content
 * @property array<array-key, mixed>|null $tool_calls
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ChatConversation $conversation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereChatConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereToolCalls($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereUpdatedAt($value)
 */
	class ChatMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $customer_id
 * @property int $vendor_id
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\SellerProfile $vendor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereLastMessageAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereVendorId($value)
 */
	class Conversation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property int $value
 * @property int $min_order_amount
 * @property int|null $usage_limit
 * @property int $used_count
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereMinOrderAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereUsageLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereUsedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coupon whereValue($value)
 */
	class Coupon extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereUserId($value)
 */
	class Favorite extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity_change
 * @property string $reason
 * @property int $quantity_after
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereQuantityAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereQuantityChange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereUpdatedAt($value)
 */
	class InventoryMovement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Conversation $conversation
 * @property-read \App\Models\User $sender
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 */
	class Message extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $order_number
 * @property int $user_id
 * @property int|null $coupon_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $archived_at
 * @property int $subtotal
 * @property int $shipping_fee
 * @property int $discount_amount
 * @property int $total
 * @property string $shipping_name
 * @property string $shipping_phone
 * @property string $shipping_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Coupon|null $coupon
 * @property-read string $formatted_total
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Payment|null $payment
 * @property-read \App\Models\Shipment|null $shipment
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCouponId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUserId($value)
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int $vendor_id
 * @property string $product_name
 * @property int $unit_price
 * @property int $quantity
 * @property int $subtotal
 * @property int|null $commission_amount
 * @property int|null $vendor_earning
 * @property string|null $commission_settled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $formatted_subtotal
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Review|null $review
 * @property-read \App\Models\SellerProfile $vendor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCommissionAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCommissionSettledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereVendorEarning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereVendorId($value)
 */
	class OrderItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $phone
 * @property string $code
 * @property \Illuminate\Support\Carbon $expires_at
 * @property bool $is_used
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereIsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereUpdatedAt($value)
 */
	class Otp extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property string $method
 * @property string $status
 * @property int $amount
 * @property string|null $transaction_ref
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTransactionRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $commission_rate
 * @property int $story_fee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $formatted_story_fee
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting whereCommissionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting whereStoryFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSetting whereUpdatedAt($value)
 */
	class PlatformSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $vendor_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $sku
 * @property int $price
 * @property int|null $compare_at_price
 * @property int $stock_quantity
 * @property int $low_stock_threshold
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $admin_approved_at
 * @property string|null $rejection_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Favorite> $favorites
 * @property-read int|null $favorites_count
 * @property-read float|null $average_rating
 * @property-read int|null $discount_percentage
 * @property-read string|null $formatted_compare_at_price
 * @property-read string $formatted_price
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductImage> $images
 * @property-read int|null $images_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryMovement> $inventoryMovements
 * @property-read int|null $inventory_movements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read \App\Models\ProductImage|null $primaryImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductQuestion> $questions
 * @property-read int|null $questions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read \App\Models\SellerProfile $vendor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product applySort(?string $sort)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product pendingReview()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product priceBetween($min, $max)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product publiclyVisible()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereAdminApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCompareAtPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereLowStockThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereRejectionNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStockQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereVendorId($value)
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property bool $is_primary
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereUpdatedAt($value)
 */
	class ProductImage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property string $question
 * @property int $helpful_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductQuestionAnswer> $answers
 * @property-read int|null $answers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductQuestionHelpfulVote> $helpfulVotes
 * @property-read int|null $helpful_votes_count
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereHelpfulCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestion whereUserId($value)
 */
	class ProductQuestion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_question_id
 * @property int $user_id
 * @property string $answer
 * @property bool $is_seller_answer
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ProductQuestion|null $question
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereIsSellerAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereProductQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionAnswer whereUserId($value)
 */
	class ProductQuestionAnswer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_question_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ProductQuestion $question
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote whereProductQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductQuestionHelpfulVote whereUserId($value)
 */
	class ProductQuestionHelpfulVote extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property int $order_item_id
 * @property int $rating
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\OrderItem $orderItem
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUserId($value)
 */
	class Review extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $store_name
 * @property string|null $store_description
 * @property string|null $store_logo
 * @property string|null $store_banner
 * @property string|null $commercial_register
 * @property string $status
 * @property int|null $commission_rate
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $effective_commission_rate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Story> $stories
 * @property-read int|null $stories_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Wallet|null $wallet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WithdrawalRequest> $withdrawalRequests
 * @property-read int|null $withdrawal_requests_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereCommercialRegister($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereCommissionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereStoreBanner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereStoreDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereStoreLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereStoreName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SellerProfile whereUserId($value)
 */
	class SellerProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int|null $shipping_company_id
 * @property string|null $tracking_number
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $shipped_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\ShippingCompany|null $shippingCompany
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereDeliveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereShippedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereShippingCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereTrackingNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shipment whereUpdatedAt($value)
 */
	class Shipment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCompany whereUpdatedAt($value)
 */
	class ShippingCompany extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $vendor_id
 * @property string $image
 * @property string|null $caption
 * @property int|null $amount_paid
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SellerProfile|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story visible()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereCaption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereVendorId($value)
 */
	class Story extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $phone
 * @property string|null $address
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property string|null $email
 * @property string|null $password
 * @property string $role
 * @property string|null $avatar
 * @property string $status
 * @property string|null $employee_status
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AppNotification> $appNotifications
 * @property-read int|null $app_notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CartItem> $cartItems
 * @property-read int|null $cart_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Favorite> $favorites
 * @property-read int|null $favorites_count
 * @property-read string $name
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \App\Models\SellerProfile|null $sellerProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User customers()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User sellers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmployeeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $vendor_id
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $formatted_balance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\SellerProfile $vendor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereVendorId($value)
 */
	class Wallet extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $wallet_id
 * @property string $type
 * @property string $reason
 * @property int $amount
 * @property int $balance_after
 * @property int|null $order_item_id
 * @property int|null $withdrawal_request_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $formatted_amount
 * @property-read \App\Models\OrderItem|null $orderItem
 * @property-read \App\Models\Wallet $wallet
 * @property-read \App\Models\WithdrawalRequest|null $withdrawalRequest
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransaction whereWithdrawalRequestId($value)
 */
	class WalletTransaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $vendor_id
 * @property int $amount
 * @property string $status
 * @property string|null $note
 * @property string|null $admin_note
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $formatted_amount
 * @property-read \App\Models\SellerProfile $vendor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereAdminNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WithdrawalRequest whereVendorId($value)
 */
	class WithdrawalRequest extends \Eloquent {}
}

