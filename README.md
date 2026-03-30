# SharedLicense provisioning module for HostBill

Module provisioning license cho HostBill, tích hợp với **SharedLicense Reseller API**.

## Tính năng

- Provisioning: **Create / Suspend / Unsuspend / Terminate / Renew**.
- **Change IP** (có giới hạn số lần đổi IP phía HostBill).
- Đồng bộ thông tin license từ API: status, key, IP, renew date, commands…
- **Admin UI**: hiển thị license details qua AJAX + actions (Renew/Change IP/Reset IP counter/Refresh).
- **Client widgets** tự đăng ký (License Details / Change IP / License Docs) và tự gán vào các sản phẩm dùng module.
- Lấy danh sách product/config options từ API, có cache fallback `products.json`.

## Yêu cầu

- HostBill (module type: `LicenseModule`).
- PHP có extensions:
	- `curl`
	- `json`
- Server HostBill có thể gọi outbound HTTPS đến API base URL.

## Cấu trúc thư mục

Trong workspace này module nằm tại `sharedlicense/` với các file chính:

- `class.sharedlicense.php`: logic provisioning, mapping dữ liệu, widgets registration.
- `class.api.php`: HTTP client gọi SharedLicense API (Bearer token + JSON).
- `admin/class.sharedlicense_controller.php`: controller Admin (ajax license details, actions).
- `templates/`:
	- `license.tpl`: chèn block UI vào trang service trong Admin.
	- `ajax.license.tpl`: HTML render license details & bootbox forms.
	- `license.js`: AJAX loader + handlers (refresh/copy/change IP/renew/reset).
- `widgets/`:
	- `class.sharedlicense_widget.php`: base widget.
	- `sl_licensedetails/`, `sl_changeip/`, `sl_licensedocs/`: widgets cho Client Area.
- `products.json`: cache fallback (sinh tự động khi gọi API thành công).

## API & Auth

Module gọi API theo header:

- `Authorization: Bearer <token>`
- `Accept: application/json`

Base URL mặc định:

- `https://sharedlicense.com/client/modules/addons/LicReseller/api`

Có thể override trong cấu hình **Server** của HostBill.

## Cài đặt

1. Copy thư mục module `sharedlicense/` vào đúng vị trí modules của HostBill, ví dụ:
	 - `.../includes/modules/Hosting/sharedlicense/`

2. Trong HostBill Admin:
	 - Vào **Settings → Modules** (hoặc trang quản lý modules tùy phiên bản HostBill)
	 - Enable module **SharedLicense**.

3. Tạo/Chọn **Server** cho module:
	 - **API Base URL** (Server Hostname)
	 - **Bearer Token** (Server Username)

4. Gán module SharedLicense cho Product trong HostBill như các module provisioning khác.

5. (Khuyến nghị) Thử **Test Connection** trước khi đặt hàng thật.

## Cấu hình trong HostBill

### 1) Server fields (kết nối API)

Trong module, các field được map như sau (xem `SharedLicense::$serverFieldsDescription`):

- **Hostname** → `API Base URL`
- **Username** → `Bearer Token`

Ghi chú:

- Password/IPAddress không dùng.

### 2) Options / Resources (cấu hình per-service)

Các option chính (xem `SharedLicense::$options`):

- `product` (**loadable**): chọn Product ID từ catalog của SharedLicense.
	- Danh sách được load từ API `GET /products`.
- `ip`: Licensed IP.
	- Dùng trong payload order và hiển thị.
	- Module sẽ cố lấy từ (ưu tiên cao → thấp): resource `ip` → account config `ip` → extra_details `license_ip` → NAT IP → account domain (nếu là IP).
- `new_ip`: New IP Address (dùng cho action Change IP).
- `max_ip_changes`: giới hạn số lần đổi IP phía HostBill.
	- `0` = không giới hạn.
- `suspend_reason`: lý do suspend gửi lên API.

### 3) Custom fields động theo Product (SharedLicense customfields)

Khi chọn `product`, module sẽ đọc `customfields` mà API trả về cho product đó và **tạo option động** dạng:

- `sharedlicense_cf_<fieldId>`

Ví dụ: `sharedlicense_cf_12`.

Giá trị custom field khi order được build theo thứ tự:

1. Nếu bạn cấu hình option `sharedlicense_cf_<id>` → dùng giá trị đó.
2. Nếu không cấu hình, module sẽ **guess** theo “role” (xem `detectCustomFieldRole`):
	 - `ip` → lấy Licensed IP
	 - `hostname` → lấy domain/hostname của service (`account_details['domain']`)
	 - `license_key` → dùng `license_key` đã có; nếu chưa có thì dùng `HB-<serviceId>`

Nếu field bắt buộc (`required`) mà không thể suy ra giá trị hợp lệ, Create sẽ fail với lỗi.

### 4) Config options theo Product (SharedLicense configOptions)

Nếu API trả về `configOptions`, module sẽ gửi `configoptions` trong payload order:

- Ưu tiên `default` nếu có.
- Nếu không có default, module chọn option đầu tiên trong danh sách `options`.

## Luồng provisioning & hành vi

### Create (Order)

File: `class.sharedlicense.php` → `Create()`

- Load product được chọn.
- Build payload:
	- `customfields` (object)
	- `configoptions` (object)
- Gọi API: `POST /products/{id}/order`
- Lưu các trường chính vào extra_details:
	- `remote_service_id`, `product_id`, `product_name`, `product_logo`, `status`, `message`…
- Sau đó gọi `syncRemoteLicenseDetails()` để đồng bộ thông tin.

**Cảnh báo quan trọng**: action Create có thể tạo license tính phí ở SharedLicense. Không test Create trên product trả phí nếu không chủ đích.

### Suspend / Unsuspend / Terminate

- Suspend → `POST /licenses/{id}/suspend` (có thể kèm `reason`).
- Unsuspend → `POST /licenses/{id}/unsuspend`.
- Terminate → `POST /licenses/{id}/cancel`.

Module sẽ cập nhật `status`, `last_action`, `last_remote_action`, `message` và (thường) sync lại details.

### Renewal / RenewNow

- `Renewal()` / `RenewNow()` → `POST /licenses/{id}/renew`.

### Change IP

- Admin: action `changeip` trong `admin/class.sharedlicense_controller.php`.
- Client: widget `sl_changeip`.

Quy tắc:

- Validate IP bằng `FILTER_VALIDATE_IP`.
- Nếu `max_ip_changes > 0` và `change_ip_count >= max_ip_changes` → chặn (HostBill-side).
- Gọi API: `POST /licenses/{id}/change-ip` với payload `{ "ip": "x.x.x.x" }`.
- Tăng `change_ip_count` và lưu IP mới vào `license_ip` + cập nhật config `ip`.

### Reset IP Counter (HostBill-side)

- Action admin `resetipcount`.
- Chỉ reset `change_ip_count` ở extra_details, **không gọi API**.

## Dữ liệu lưu trong service (extra_details)

Module lưu các keys trong `SharedLicense::$details` (một phần):

- `remote_service_id`
- `license_key`
- `license_ip`
- `product_id`, `product_name`, `product_logo`
- `status`, `message`
- `change_ip_count`, `change_ip_limit` (remote)
- `auto_renew`, `renew_date`, `reg_date`
- `suspended_reason`
- `commands_json` (raw JSON của lệnh cài đặt)
- `last_action`, `last_remote_action`

Những giá trị này được update bằng `syncRemoteLicenseDetails()` và hiển thị ở Admin/Widgets.

## Admin UI

### Hiển thị license block trong trang service

- Template injection: `templates/license.tpl`
- AJAX body: `templates/ajax.license.tpl`
- JS loader: `templates/license.js`

Admin UI có các nút:

- Refresh Data
- Change IP
- Reset IP Counter
- Renew Now
- Copy installation commands

Endpoint phía admin:

- `?cmd=sharedlicense&action=license&id=<serviceId>` → render `ajax.license.tpl`
- `?cmd=sharedlicense&action=changeip&id=<serviceId>`
- `?cmd=sharedlicense&action=renew&id=<serviceId>`
- `?cmd=sharedlicense&action=resetipcount&id=<serviceId>`

## Client Widgets

Widgets được đăng ký khi module upgrade (xem `registerClientWidgets()`):

1. `sl_licensedetails`: hiển thị thông tin license & Renew Now.
2. `sl_changeip`: form đổi IP, có check limit.
3. `sl_licensedocs`: hiển thị installation commands.

Module cố gắng auto-assign các widget này vào mọi Product đang dùng SharedLicense module.

## Cache product catalog (`products.json`)

- Lần đầu gọi `GET /products` thành công, module sẽ ghi response vào `products.json`.
- Nếu API lỗi/timeout, module sẽ fallback đọc `products.json` để vẫn load được danh sách product.

Khuyến nghị:

- Không chỉnh sửa `products.json` thủ công trừ khi bạn hiểu format.
- Có thể xóa file để buộc module reload catalog từ API.

## Logging & Debug

Nếu HostBill có `HBDebug::debug`, module sẽ log request/response:

- Request log che token: `Bearer ***`.

Khi gặp lỗi:

1. Xác nhận token đúng và còn hạn.
2. Xác nhận base URL đúng (không dư dấu `/`).
3. Kiểm tra firewall/outbound rules.
4. Thử `Test Connection` trong HostBill.

## Troubleshooting (các lỗi thường gặp)

### 1) “API token is empty”

- Chưa nhập **Bearer Token** trong Server Username.

### 2) “Selected product does not exist…”

- Product ID cấu hình không còn tồn tại trên SharedLicense.
- API không load được catalog và cache `products.json` không có.

### 3) Create fail vì thiếu custom field bắt buộc

- Product yêu cầu custom field `required`.
- Hãy cấu hình option `sharedlicense_cf_<id>` hoặc đảm bảo module có thể guess được (IP/hostname/license_key).

### 4) Không đổi được IP (limit)

- `max_ip_changes` phía HostBill đã đạt ngưỡng.
- Có thể dùng “Reset IP Counter” (chỉ reset local counter) nếu policy cho phép.

## Security notes

- Bearer token là secret: chỉ lưu trong Server config của HostBill.
- Module không ghi token ra log (được mask).
- Không expose endpoint trực tiếp; action admin yêu cầu `token_valid` cho các thao tác thay đổi (changeip/renew/reset).

## Gợi ý vận hành

- Nên tạo một product test (miễn phí / sandbox) trên SharedLicense để test Create.
- Khi cần đồng bộ lại dữ liệu license, dùng **Refresh Data** (admin) hoặc thêm `&refresh=1` cho widget.

## Version

- Current module version: `1.0.0` (xem `SharedLicense::$version`).

