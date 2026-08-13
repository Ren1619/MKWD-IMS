export type HrisReference = {
    id: number;
    external_id?: string;
    type?: string;
    code: string | null;
    name: string;
    email?: string | null;
    is_active?: boolean;
    last_synced_at?: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export type MajorCategory = {
    inv_mjr_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    class_categories_count?: number;
    class_categories?: ClassCategory[];
};

export type ClassCategory = {
    inv_class_cat_id: number;
    inv_mjr_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    series_categories_count?: number;
    series_categories?: SeriesCategory[];
    major_category?: MajorCategory;
};

export type SeriesCategory = {
    inv_series_cat_id: number;
    inv_class_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    items_count?: number;
    class_category?: ClassCategory;
};

export type AssetCategory = {
    inv_asset_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    assets_count?: number;
};
