<?php

namespace App\Enums;

enum CmsPermissionEntity: string
{
    case Category = 'category';
    case Banner = 'banner';
    case Product = 'product';
    case Filter = 'filter';
    case Attribute = 'attribute';
    case Option = 'option';
    case Manufacturer = 'manufacturer';
    case Ingredient = 'ingredient';
    case Effect = 'effect';
    case Safety = 'safety';
    case Skincare = 'skincare';
    case Contact = 'contact';
    case Review = 'review';
    case ReviewCriteria = 'review-criteria';
    case ReviewTag = 'review-tag';
    case Blog = 'blog';
    case BlogCategory = 'blog-category';
    case Menu = 'menu';
    case MenuValue = 'menu-value';
    case Order = 'order';
    case OrderStatus = 'order-status';
    case Carrier = 'carrier';
    case Payment = 'payment';
    case Zone = 'zone';
    case District = 'district';
    case Ward = 'ward';
    case Information = 'information';
    case Warehouse = 'warehouse';
    case Setting = 'setting';
    case User = 'user';
    case StoreReview = 'store-review';
    case Role = 'role';
    case UserGroup = 'user-group';
    case Customer = 'customer';
    // Marketing (2026-08) — convert từ mt219 + 2 màn mới của infun.
    case Coupon = 'coupon';
    case Voucher = 'voucher';
    case VoucherTheme = 'voucher-theme';
    case Gift = 'gift';
    case VoucherRewardRule = 'voucher-reward-rule';
    case MailCampaign = 'mail-campaign';

    public const ACTIONS = ['list', 'detail', 'create', 'edit', 'del'];

    public function permissionCodes(): array
    {
        return array_map(
            fn (string $action): string => $action . '-' . $this->value,
            self::ACTIONS,
        );
    }

    public static function allPermissionCodes(): array
    {
        return array_merge(
            ...array_map(
                static fn (self $entity): array => $entity->permissionCodes(),
                self::cases(),
            ),
        );
    }

    public static function slugs(): array
    {
        return array_map(static fn (self $entity): string => $entity->value, self::cases());
    }
}
