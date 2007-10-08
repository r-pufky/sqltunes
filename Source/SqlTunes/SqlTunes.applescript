-- When the start button is pressed
on clicked theObject
	-- grab the user's information when "start" is clicked
	set theWindow to window of theObject
	tell theWindow
		set server to contents of text field "Server" as text
		set db to contents of text field "Database" as text
		set user to contents of text field "Username" as text
		set pass to contents of text field "Password" as text
		set loglevel to the name of the current cell of matrix 1 as text
	end tell
	
	-- hide the input window
	set visible of theWindow to false
	
	-- format data for output, and write
	set tdata to "Server=" & server & return & "Database=" & db & return & Â
		"Username=" & user & return & "Password=" & pass & return & Â
		"Log=" & loglevel
	do shell script "echo '" & tdata & "' > /tmp/.connect.sqltunes"
	
	-- launch the SqlTunes Engine
	do shell script "open /Applications/.SqlTunes/Interfaces/engine.app &"
	quit
end clicked

-- Quit if the window is closed
on should quit after last window closed theObject
	return true
end should quit after last window closed