# User permissions and inheritance

In **Administrator → Roles & Permissions → Users → Show Permissions**, ON means the configured grant comes from an active assigned role, the user's department, or an additional user grant. Campus and record ownership restrictions still apply when using the corresponding module.

Inherited permissions show a **Role**, **Dept.**, or **Role + Dept.** badge. Hover/tap the badge to see the source names. These switches are ON and locked in the user panel; change them in **Permissions by Role** or **Permissions by Dept.**. This applies to main menus, submenus, actions, and nested Scholarship tabs.

Extra user grants remain editable. **Save User Permissions** saves only these extra grants. It does not copy inherited grants into the user record, so changes to a role or department remain reflected after refreshing the user panel. Existing user grants that overlap inherited permissions are retained when saving. An inherited parent menu or tab can enable additional user-only actions.

**Full Access** reflects the combined configured grants. Turning it OFF clears extra choices while keeping inherited choices ON. Fully inherited Full Access is controlled at the role/department source. The Permission by User print/Excel report includes inherited grants and identifies their sources.

Deploy the PHP/Blade changes and run `npm run build` for the updated permission toggles. No database migration is required for this inheritance display/save update.
