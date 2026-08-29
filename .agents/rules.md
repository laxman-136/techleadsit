# Project Rules & Safeguards

To prevent any service disruptions or critical errors on the live landing pages, all AI agents working on this codebase must adhere to the following rules before committing code or pushing to GitHub:

## 1. Mandatory Pre-Commit PHP Syntax Validation
You must check the PHP syntax of `techleadsit.php` using the local XAMPP PHP binary to ensure it contains no compilation or parsing typos.
* **Command to run**:
  ```powershell
  C:\xampp\php\php.exe -l techleadsit.php
  ```
* **Constraint**: Do not commit if any syntax warnings or errors are returned.

## 2. Mandatory Pre-Commit Runtime Dry-Run
Run the mock WordPress runner to check for execution/runtime errors (such as mismatched arrays, duplicate keys, or invalid function calls) before pushing.
* **Command to run**:
  ```powershell
  C:\xampp\php\php.exe run_test.php
  ```

## 3. Verify All Variants Locally First
Ensure that new slugs are registered properly, all replacement arrays are balanced, and they run successfully on the mock runner before any deployment.
