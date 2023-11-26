# 驗證 iOS 裝置的訂單

## 簡介

這個 PHP 代碼用於驗證 iOS 裝置的訂單收據。它提供了一個方便的方式來檢查 iOS 購買是否有效。

## 使用方法

1. **準備環境**：

   在使用此庫之前，請確保你已經完成以下事項：

   - 替換 `WalletRecharge` 和 `WalletRechargeLog` 為你使用的 ORM 或資料表。
   - 將 `$config` 替換為你的應用程序的配置。

2. **示例代碼**：

   使用以下示例代碼檢查 iOS 訂單收據的有效性：

   ```php
   <?php

   use Library\ApplePay;

   // 初始化 ApplePay 對象
   $applePay = new ApplePay();

   // 檢查收據是否有效
   try {
       $receipt = 'base64_encoded_receipt_data';
       $receiptData = $applePay->getReceiptData($receipt);

       // 驗證購買信息
       $isValid = $applePay->checkInfo($receiptData, $walletRechargeId);

       if ($isValid) {
           // 收據有效，執行相應操作
           // ...
       } else {
           // 收據無效，處理錯誤
           // ...
       }
   } catch (Exception $e) {
       // 發生錯誤，處理異常情況
       // ...
   }
