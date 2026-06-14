import apiClient from '@/lib/api-client';

export type CommerceProduct = {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  price_cents: number;
  price_label: string;
  vendor_name: string | null;
  source_type: string;
  external_product_url: string | null;
  affiliate_tracking_url: string | null;
  image_url: string | null;
  category: string | null;
  requires_customization: boolean;
  fulfillment_type: string;
  commission_rate: string | null;
  customization_schema: Record<string, unknown>;
  external_checkout_required: boolean;
  metadata: Record<string, unknown>;
};

export type CommerceCartItem = {
  id: number;
  product_id: number;
  title: string;
  image_url: string | null;
  source_type: string;
  quantity: number;
  selected_options: Record<string, unknown>;
  custom_design_data: Record<string, unknown>;
  unit_price_label: string;
  estimated_total_label: string;
  vendor_name: string | null;
  external_checkout_required: boolean;
  affiliate_tracking_url: string | null;
  fulfillment_type: string;
  metadata: Record<string, unknown>;
};

export type CommerceCheckoutGroup = {
  label: string;
  items: CommerceCartItem[];
  total_cents: number;
  total_label: string;
  item_count: number;
};

export type CommerceCart = {
  id: number;
  status: string;
  items: CommerceCartItem[];
  item_count: number;
  estimated_total_cents: number;
  estimated_total_label: string;
  checkout_groups: Record<string, CommerceCheckoutGroup>;
};

export async function fetchCommerceProducts() {
  const response = await apiClient.get<{ products: CommerceProduct[] }>('/commerce/products');
  return response.data.products;
}

export async function fetchCommerceCart() {
  const response = await apiClient.get<{ cart: CommerceCart }>('/commerce/cart');
  return response.data.cart;
}

export async function addCommerceCartItem(payload: {
  product_id: number;
  quantity?: number;
  selected_options?: Record<string, unknown>;
  custom_design_data?: Record<string, unknown>;
}) {
  const response = await apiClient.post<{ cart: CommerceCart }>('/commerce/cart/items', payload);
  return response.data.cart;
}

export async function updateCommerceCartItem(itemId: number, quantity: number) {
  const response = await apiClient.patch<{ cart: CommerceCart }>(`/commerce/cart/items/${itemId}`, { quantity });
  return response.data.cart;
}

export async function removeCommerceCartItem(itemId: number) {
  const response = await apiClient.delete<{ cart: CommerceCart }>(`/commerce/cart/items/${itemId}`);
  return response.data.cart;
}
