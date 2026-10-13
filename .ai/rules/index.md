# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/{Models/InventoryItem*,Services/InventoryStockService.php,Services/SupplyRequestWorkflow.php} | .ai/rules/app-models.md |
| app/{Services/InventoryReportService.php,Services/SupplyRequestWorkflow.php,Models/InventoryItemStockOut.php} | .ai/rules/app-services.md |
| app/** | .ai/rules/app.md |
| app/Http/Controllers/Inventory/**,routes/inventory.php,resources/js/pages/Inventory/Assets/** | .ai/rules/assets.md |
| routes/api.php,app/Console/Commands/*VehicleApiTokenCommand.php | .ai/rules/commands.md |
| resources/js/components/** | .ai/rules/components.md |
| app/{Models,Services,Http/Controllers,Http/Requests}/**/*InventoryAsset*.php, app/{Models,Services,Http/Controllers,Http/Requests}/**/*SemiExpendable*.php | .ai/rules/controllers-http-requests.md |
| resources/js/pages/Inventory/Procurement/**,resources/js/components/app-sidebar.tsx,app/Http/Controllers/Inventory/ProcurementRequestController.php | .ai/rules/controllers-inventory.md |
| app/Services/ProcurementDeliveryService.php,resources/js/pages/Inventory/Deliveries/** | .ai/rules/deliveries.md |
| app/Services/InventoryDisposalService.php,app/Http/Requests/Inventory/Record*EventRequest.php,resources/js/pages/Inventory/Disposals/** | .ai/rules/disposals.md |
| app/Services/SupplyRequestWorkflow.php,tests/Feature/SupplyRequestWorkflowTest.php | .ai/rules/feature.md |
| app/{Actions/Integrations,Integrations/Hris,Models}/** | .ai/rules/hris-models.md |
| app/{Services,Integrations/Hris,Providers}/** | .ai/rules/hris-providers.md |
| app/Integrations/Hris/** | .ai/rules/hris.md |
| resources/js/pages/Inventory/Items/** | .ai/rules/inventory-items.md |
| app/Services/ProcurementRequestWorkflow.php,app/Models/ProcurementRequestLine.php,resources/js/pages/Inventory/Procurement/** | .ai/rules/inventory-procurement.md |
| app/Services/*Liquidation*.php,app/Services/SupplyRequestWorkflow.php,resources/js/pages/Inventory/Requests/** | .ai/rules/inventory-requests.md |
| app/Models/InventoryAsset.php,app/Http/Controllers/Inventory/PropertyTagController.php,resources/views/inventory/property-tag.blade.php | .ai/rules/inventory-views-inventory.md |
| app/{Models,Services,Http/Controllers/Inventory}/**/*Accountability*.php | .ai/rules/inventory.md |
| app/Http/{Controllers,Requests}/Inventory/InventoryItem*.php,resources/js/pages/Inventory/Items/** | .ai/rules/items.md |
| app/Http/Controllers/Inventory/InventoryAssetController.php,resources/js/pages/Inventory/Assets/**,resources/js/components/app-sidebar.tsx,routes/inventory.php | .ai/rules/js-components.md |
| {app,database,resources/js}/** | .ai/rules/js.md |
| database/migrations/*property_accountability*.php | .ai/rules/migrations.md |
| app/Models/InventoryCountEntry.php | .ai/rules/models.md |
| app/Services/*procurement*.php,app/Services/ProcurementRequestWorkflow.php,resources/js/pages/Inventory/Procurement/** | .ai/rules/pages-inventory-procurement.md |
| app/Services/*Liquidation*.php,app/Models/DepartmentLiquidation.php,resources/js/pages/Inventory/Requests/** | .ai/rules/pages-inventory-requests.md |
| resources/js/pages/Inventory/Procurement/** | .ai/rules/procurement.md |
| app/Http/Controllers/Inventory/SupplyRequestController.php,resources/js/pages/Inventory/Requests/**,resources/js/components/responsibility-center-combobox.tsx | .ai/rules/requests-js-components.md |
| resources/js/pages/Inventory/{Requests,Procurement,Accountability}/** | .ai/rules/requests-procurement-accountability.md |
| app/Services/{SupplyRequestWorkflow,SupplyShortageProcurementService,ProcurementRequestWorkflow}.php,resources/js/pages/Inventory/{Requests,Procurement}/** | .ai/rules/requests-procurement.md |
| app/Http/Controllers/Inventory/SupplyRequestController.php,resources/js/pages/Inventory/Requests/Index.tsx | .ai/rules/requests.md |
| resources/js/**/*.tsx, resources/js/** | .ai/rules/resources-js.md |
| app/{Services,Integrations/Hris}/** | .ai/rules/services-integrations-hris.md |
| app/Services/Inventory*{Count,Stock}*.php, app/Services/InventoryReturnService.php, app/Services/Procurement*{Delivery,Request}*.php, app/Services/InventoryMonthlyReport*.php, app/Services/InventoryWarehouseService.php, app/Services/InventoryPropertyCardService.php, app/Services/InventoryTransferReportService.php, app/Services/InventoryPropertyTransferService.php, app/Services/InventoryAssetService.php | .ai/rules/services.md |
| app/Services/ProcurementDeliveryService.php,resources/js/pages/Inventory/Deliveries/**,resources/views/inventory/{inspection-evaluation,supplier-return-notice}.blade.php | .ai/rules/views-inventory.md |
