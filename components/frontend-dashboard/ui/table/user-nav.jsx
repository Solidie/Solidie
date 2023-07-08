import React, { useContext } from "react";
import { CreditCard, LogOut, PlusCircle, Settings, User } from "lucide-react";

import { Avatar, AvatarFallback, AvatarImage } from "../avatar.jsx";
import { Button } from "../index";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "../dropdown-menu.jsx";

export function UserNav() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className={"relative h-8 w-8 rounded-full".classNames()}>
          <Avatar className={"h-9 w-9".classNames()}>
            <AvatarImage
              id={"userProfileIMG".idNames()}
              // className={"w-8 object-contain rounded-lg shadow-lg shadow-tertiary/40".classNames()}
              src={null}
              alt="@Solidie"
            />
            <AvatarFallback>SO</AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className={"w-56".classNames()} align="end" forceMount>
        <DropdownMenuLabel className={"font-normal".classNames()}>
          <div className={"flex flex-col space-y-1".classNames()}>
            <p className={"text-sm font-medium leading-none".classNames()}>Solidie</p>
            <p className={"text-xs leading-none text-muted-foreground".classNames()}>
              s@solidie.com
            </p>
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
          <DropdownMenuItem>
            <User className={"mr-2 h-4 w-4".classNames()} />
            <span>Profile</span>
            <DropdownMenuShortcut>⇧⌘P</DropdownMenuShortcut>
          </DropdownMenuItem>
          {/* <DropdownMenuItem>
            <CreditCard className={"mr-2 h-4 w-4".classNames()} />
            <span>Billing</span>
            <DropdownMenuShortcut>⌘B</DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuItem>
            <Settings className={"mr-2 h-4 w-4".classNames()} />
            <span>Settings</span>
            <DropdownMenuShortcut>⌘S</DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuItem>
            <PlusCircle className={"mr-2 h-4 w-4".classNames()} />
            <span>New Team</span>
          </DropdownMenuItem> */}
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem>
          <LogOut className={"mr-2 h-4 w-4".classNames()} />
          <span>Log out</span>
          <DropdownMenuShortcut>⇧⌘Q</DropdownMenuShortcut>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}