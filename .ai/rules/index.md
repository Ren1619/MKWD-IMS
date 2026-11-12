# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Controllers/Inventory/SupplyRequestController.php | .ai/rules/app-http-controllers-inventory.md |
| app/{Models/InventoryItem*,Services/InventoryStockService.php,Services/SupplyRequestWorkflow.php} | .ai/rules/app-models.md |
| app/{Services/InventoryReportService.php,Services/SupplyRequestWorkflow.php,Models/InventoryItemStockOut.php} | .ai/rules/app-services.md |
| app/** | .ai/rules/app.md |
| resources/js/pages/Inventory/Assets/**,app/Http/Controllers/Inventory/InventoryAssetController.php | .ai/rules/assets-http-controllers-inventory.md |
| app/Http/Controllers/Inventory/**,routes/inventory.php,resources/js/pages/Inventory/Assets/** | .ai/rules/assets.md |
| routes/api.php,app/Console/Commands/*VehicleApiTokenCommand.php | .ai/rules/commands.md |
| resources/js/pages/Inventory/Assets/**,resources/js/components/asset-details.tsx,resources/js/components/property-history.tsx | .ai/rules/components-js-components.md |
| resources/js/components/input-validation.tsx,resources/js/components/validation-summary.tsx,resources/js/lib/input-validation.ts | .ai/rules/components-js-lib.md |
| resources/js/components/**, resources/js/components/validation-summary.tsx, resources/js/components/asset-details.tsx | .ai/rules/components.md |
| resources/js/{components,pages/Inventory}/** | .ai/rules/componentspages-inventory.md |
| app/{Models,Services,Http/Controllers,Http/Requests}/**/*InventoryAsset*.php, app/{Models,Services,Http/Controllers,Http/Requests}/**/*SemiExpendable*.php | .ai/rules/controllers-http-requests.md |
| resources/js/pages/Inventory/Procurement/**,resources/js/components/app-sidebar.tsx,app/Http/Controllers/Inventory/ProcurementRequestController.php | .ai/rules/controllers-inventory.md |
| resources/css/app.css | .ai/rules/css.md |
| resources/js/pages/Inventory/Deliveries/**,app/Http/Controllers/Inventory/ProcurementDeliveryController.php | .ai/rules/deliveries-http-controllers-inventory.md |
| app/Services/ProcurementDeliveryService.php,resources/js/pages/Inventory/Deliveries/** | .ai/rules/deliveries.md |
| app/Services/InventoryDisposalService.php,app/Http/Requests/Inventory/Record*EventRequest.php,resources/js/pages/Inventory/Disposals/** | .ai/rules/disposals.md |
| app/{Events,Listeners,Providers}/** | .ai/rules/events-listeners-providers.md |
| app/Services/SupplyRequestWorkflow.php,tests/Feature/SupplyRequestWorkflowTest.php | .ai/rules/feature.md |
| resources/js/{hooks/use-realtime-updates.ts,lib/realtime-refresh.ts,layouts/app-layout.tsx} | .ai/rules/hooks.md |
| app/{Actions/Integrations,Integrations/Hris,Models}/** | .ai/rules/hris-models.md |
| app/{Services,Integrations/Hris,Providers}/** | .ai/rules/hris-providers.md |
| app/Integrations/Hris/** | .ai/rules/hris.md |
| resources/js/pages/Inventory/Returns/**,app/Http/Controllers/Inventory/InventoryReturnController.php | .ai/rules/http-controllers-inventory.md |
| resources/js/pages/Inventory/Requests/**,app/Http/Requests/Inventory/PrintIssuanceSlipRequest.php | .ai/rules/http-requests-inventory.md |
| resources/js/components/asset-details.tsx,resources/js/pages/Inventory/Assets/Show.tsx | .ai/rules/inventory-assets.md |
| resources/js/pages/Inventory/Items/** | .ai/rules/inventory-items.md |
| app/Services/ProcurementRequestWorkflow.php,app/Models/ProcurementRequestLine.php,resources/js/pages/Inventory/Procurement/** | .ai/rules/inventory-procurement.md |
| app/{Http/Controllers/Inventory/InventoryMonthlyReportController.php,Http/Requests/Inventory/*InventoryMonthlyReportRequest.php,Services/InventoryMonthlyReportService.php} | .ai/rules/inventory-requests-inventory.md |
| app/Services/*Liquidation*.php,app/Services/SupplyRequestWorkflow.php,resources/js/pages/Inventory/Requests/** | .ai/rules/inventory-requests.md |
| app/Models/InventoryAsset.php,app/Http/Controllers/Inventory/PropertyTagController.php,resources/views/inventory/property-tag.blade.php | .ai/rules/inventory-views-inventory.md |
| app/Models/Warehouse.php,app/Http/Requests/Inventory/SaveWarehouseRequest.php,resources/js/pages/Inventory/Warehouse/** | .ai/rules/inventory-warehouse.md |
| app/{Models,Services,Http/Controllers/Inventory}/**/*Accountability*.php | .ai/rules/inventory.md |
| app/Http/{Controllers,Requests}/Inventory/InventoryItem*.php,resources/js/pages/Inventory/Items/** | .ai/rules/items.md |
| app/Http/Controllers/Inventory/InventoryAssetController.php,resources/js/pages/Inventory/Assets/**,resources/js/components/app-sidebar.tsx,routes/inventory.php | .ai/rules/js-components.md |
| resources/js/components/input-validation.tsx,resources/js/lib/input-validation.ts | .ai/rules/js-lib.md |
| {app,database,resources/js}/** | .ai/rules/js.md |
| resources/js/{components/record-change-badge.tsx,lib/record-changes.ts,hooks/use-realtime-updates.ts} | .ai/rules/jscomponents.md |
| app/Http/Requests/**,app/Rules/InputFormat.php,resources/js/components/input-validation.tsx,resources/js/lib/input-validation.ts,resources/input-formats.json | .ai/rules/lib.md |
| database/migrations/*property_accountability*.php | .ai/rules/migrations.md |
| app/{Models/InventoryAsset.php,Http/Requests/Inventory/*InventoryAsset*.php,Services/*Property*Service.php} | .ai/rules/models-requests-inventory.md |
| app/Models/InventoryCountEntry.php | .ai/rules/models.md |
| app/Services/InventoryMonthlyReport*.php,resources/js/pages/Inventory/MonthlyReports/** | .ai/rules/monthly-reports.md |
| resources/js/pages/Inventory/Assets/** | .ai/rules/pages-inventory-assets.md |
| app/Services/*procurement*.php,app/Services/ProcurementRequestWorkflow.php,resources/js/pages/Inventory/Procurement/** | .ai/rules/pages-inventory-procurement.md |
| app/Services/*Liquidation*.php,app/Models/DepartmentLiquidation.php,resources/js/pages/Inventory/Requests/** | .ai/rules/pages-inventory-requests.md |
| resources/js/pages/Inventory/Warehouse/** | .ai/rules/pages-inventory-warehouse.md |
| resources/js/pages/Inventory/Procurement/** | .ai/rules/procurement.md |
| resources/js/pages/Inventory/{Requests,Returns}/**,app/{Services/InventoryReturnService.php,Http/Controllers/Inventory/InventoryReturnController.php,Http/Requests/Inventory/StoreInventoryReturnRequest.php} | .ai/rules/requests-inventory.md |
| app/Http/Controllers/Inventory/SupplyRequestController.php,resources/js/pages/Inventory/Requests/**,resources/js/components/responsibility-center-combobox.tsx | .ai/rules/requests-js-components.md |
| resources/js/pages/Inventory/{Requests,Procurement,Accountability}/** | .ai/rules/requests-procurement-accountability.md |
| app/Services/{SupplyRequestWorkflow,SupplyShortageProcurementService,ProcurementRequestWorkflow}.php,resources/js/pages/Inventory/{Requests,Procurement}/** | .ai/rules/requests-procurement.md |
| resources/js/pages/Inventory/{Requests,Returns}/** | .ai/rules/requests-returns.md |
| app/Http/Controllers/Inventory/SupplyRequestController.php,resources/js/pages/Inventory/Requests/Index.tsx | .ai/rules/requests.md |
| app/{Models,Services,Http/Controllers/Inventory}/**,resources/{js/pages/Inventory/Requests,views/inventory}/** | .ai/rules/requestsviews-inventory.md |
| resources/js/**/*.tsx, resources/js/** | .ai/rules/resources-js.md |
| resources/js/pages/Inventory/Returns/** | .ai/rules/returns.md |
| app/{Services,Integrations/Hris}/** | .ai/rules/services-integrations-hris.md |
| resources/js/pages/Inventory/Returns/**,app/Services/InventoryReturnService.php,resources/views/inventory/material-return.blade.php | .ai/rules/services-views-inventory.md |
| app/Services/Inventory*{Count,Stock}*.php, app/Services/InventoryReturnService.php, app/Services/Procurement*{Delivery,Request}*.php, app/Services/InventoryMonthlyReport*.php, app/Services/InventoryWarehouseService.php, app/Services/InventoryPropertyCardService.php, app/Services/InventoryTransferReportService.php, app/Services/InventoryPropertyTransferService.php, app/Services/InventoryAssetService.php, app/Services/TemporaryHeadApprovalAccess.php | .ai/rules/services.md |
| app/Services/ProcurementDeliveryService.php,resources/js/pages/Inventory/Deliveries/**,resources/views/inventory/{inspection-evaluation,supplier-return-notice}.blade.php | .ai/rules/views-inventory.md |
| resources/views/** | .ai/rules/views.md |
| app/{Services/InventoryWarehouseService.php,Http/Controllers/Inventory/WarehouseRegistryController.php},resources/js/pages/Inventory/Warehouse/** | .ai/rules/warehouse.md |
